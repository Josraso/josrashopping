<?php
/**
 * Josra Shopping Feed — generación de los feeds XML para Google Merchant Center
 *
 * Basado en gshoppingflux (Copyright 2014-2026 Google Shopping Flux Contributors), Apache License 2.0.
 * Modificado por josra: reescrito según la especificación de datos de producto de Google (2026):
 * GTIN validado, identifier_exists bien calculado, MPN del fabricante, backorder con fecha,
 * categoría de Google por ID, product_detail, custom_label, sale_price_effective_date,
 * sin atributos que Google no admite y con diagnóstico de los productos con problemas.
 *
 * @author    josra
 * @license   Apache License 2.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class JssFeed
{
    const ISSUES = 'josrashopping_issue';

    const TITLE_MAX = 150;
    const DESCRIPTION_MAX = 5000;
    const PRODUCT_TYPE_MAX = 750;
    const MAX_IMAGES = 11; // la principal y 10 adicionales
    const BATCH = 200;

    /** Problemas que se apuntan en el diagnóstico: código => array(nivel: 2 no se envía, 1 aviso, 0 sugerencia; texto; cómo arreglarlo) */
    public static function issueTypes()
    {
        return array(
            'no_image' => array(2, 'Sin imagen', 'Google exige al menos una imagen. Sube una imagen al producto (o a la combinación).'),
            'no_price' => array(2, 'Sin precio', 'El precio es 0. Pon un precio de venta al producto.'),
            'not_orderable' => array(2, 'No disponible para pedidos', 'En la ficha está desmarcado «Disponible para pedidos». Márcalo si quieres anunciarlo.'),
            'invalid_gtin' => array(1, 'EAN/UPC no válido', 'El código de barras tiene mal el dígito de control o la longitud. Se envía sin GTIN; corrígelo en la ficha (pestaña Referencias).'),
            'no_brand' => array(1, 'Sin marca', 'Google pide la marca en productos nuevos. Asigna un fabricante o activa «Marca propia» en la configuración.'),
            'no_description' => array(1, 'Sin descripción', 'La descripción está vacía; se envía el nombre del producto. Escribe una descripción.'),
            'no_google_category' => array(0, 'Sin categoría de Google', 'Su categoría no tiene categoría de Google asignada. Google la deduce sola, pero asignarla mejora la clasificación.'),
            'title_cut' => array(1, 'Título cortado', 'El título pasa de 150 caracteres y se ha cortado.'),
        );
    }

    public static function installTable()
    {
        return Db::getInstance()->execute('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::ISSUES . '` (
            `id_shop` int(10) unsigned NOT NULL,
            `id_product` int(10) unsigned NOT NULL,
            `id_product_attribute` int(10) unsigned NOT NULL DEFAULT 0,
            `code` varchar(32) NOT NULL,
            `detail` varchar(255) NOT NULL DEFAULT "",
            PRIMARY KEY (`id_shop`, `id_product`, `id_product_attribute`, `code`),
            KEY `idx_code` (`id_shop`, `code`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8');
    }

    /** @var Josrashopping */
    protected $module;
    protected $idShop;
    protected $conf;
    protected $context;
    protected $categories;
    protected $currency;
    protected $country;
    protected $shopName;
    protected $recordIssues = false;
    protected $issues = array();
    protected $stats = array();
    protected $brandCache = array();
    protected $carrierCache = array();
    protected $featureNames = array();
    protected $isoUnitsWeight = array('kg', 'g', 'lb', 'oz');

    public function __construct($module, $idShop)
    {
        $this->module = $module;
        $this->idShop = (int) $idShop;
        $this->context = Context::getContext();
    }

    /** Carpeta y nombre de archivo de un feed */
    public static function filePath($idShop, $langIso, $currencyIso)
    {
        $key = preg_replace('/[^a-z0-9]/', '', Tools::strtolower(JssSettings::get('JSS_FEED_KEY')));

        return dirname(__FILE__) . '/../export/feed_' . $key . '_' . (int) $idShop . '_'
            . Tools::strtolower(preg_replace('/[^a-zA-Z]/', '', $langIso)) . '_' . Tools::strtolower(preg_replace('/[^a-zA-Z]/', '', $currencyIso)) . '.xml';
    }

    /** Feeds configurados de la tienda: lista de array('id_lang', 'lang', 'currency', 'file', 'url') */
    public static function feeds($idShop)
    {
        $currency = new Currency((int) JssSettings::get('JSS_CURRENCY'));
        if (!Validate::isLoadedObject($currency)) {
            $currency = new Currency((int) Configuration::get('PS_CURRENCY_DEFAULT'));
        }
        $out = array();
        foreach (JssSettings::ids('JSS_LANGS') as $idLang) {
            $lang = new Language($idLang);
            if (!Validate::isLoadedObject($lang)) {
                continue;
            }
            $out[] = array(
                'id_lang' => (int) $idLang,
                'lang' => $lang->iso_code,
                'lang_name' => $lang->name,
                'currency' => $currency->iso_code,
                'id_currency' => (int) $currency->id,
                'file' => self::filePath($idShop, $lang->iso_code, $currency->iso_code),
                'url' => Context::getContext()->link->getModuleLink('josrashopping', 'feed', array(
                    'k' => JssSettings::get('JSS_FEED_KEY'),
                    'l' => $lang->iso_code,
                    'c' => $currency->iso_code,
                ), true, null, (int) $idShop),
            );
        }

        return $out;
    }

    /**
     * Genera todos los feeds de la tienda (uno por idioma). Con bloqueo para no solaparse.
     *
     * @return array resumen (también se guarda en JSS_LAST_RUN)
     */
    public function generateAll()
    {
        $lock = @fopen(dirname(__FILE__) . '/../export/.lock_' . $this->idShop, 'c');
        if ($lock && !flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);

            return array('error' => 'Ya se está generando el feed en este momento. Espera un par de minutos.');
        }
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');
        $start = microtime(true);

        $this->conf = JssSettings::all();
        $feeds = self::feeds($this->idShop);
        $result = array('time' => time(), 'feeds' => array(), 'error' => '');
        if (!$feeds) {
            $result['error'] = 'No hay ningún idioma elegido para el feed.';
        }

        $backup = array($this->context->language, $this->context->currency, $this->context->country, $this->context->shop);
        $first = true;
        foreach ($feeds as $feed) {
            try {
                $this->recordIssues = $first;
                $stats = $this->generateOne($feed);
                $result['feeds'][] = array('lang' => $feed['lang'], 'currency' => $feed['currency'], 'stats' => $stats);
                if ($first) {
                    $result['stats'] = $stats;
                }
            } catch (Exception $e) {
                $result['error'] .= ($result['error'] !== '' ? ' ' : '') . 'Feed ' . Tools::strtoupper($feed['lang']) . ': ' . $e->getMessage();
            }
            $first = false;
        }
        list($this->context->language, $this->context->currency, $this->context->country, $this->context->shop) = $backup;

        $result['seconds'] = round(microtime(true) - $start, 1);
        JssSettings::saveLastRun($result);
        if ($lock) {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return $result;
    }

    /** Genera el feed de un idioma */
    protected function generateOne(array $feed)
    {
        $idLang = (int) $feed['id_lang'];
        $this->stats = array('items' => 0, 'products' => 0, 'excluded' => array(), 'warnings' => 0);
        $this->issues = array();

        $shop = new Shop($this->idShop);
        $this->context->shop = $shop;
        $this->context->language = new Language($idLang);
        $this->currency = new Currency((int) $feed['id_currency']);
        $this->context->currency = $this->currency;
        $this->country = new Country((int) $this->conf['JSS_COUNTRY']);
        if (!Validate::isLoadedObject($this->country)) {
            $this->country = new Country((int) Configuration::get('PS_COUNTRY_DEFAULT'));
        }
        $this->context->country = $this->country;
        if (!$this->context->link) {
            $this->context->link = new Link();
        }
        $this->shopName = Configuration::get('PS_SHOP_NAME', null, null, $this->idShop);
        $this->categories = JssCategories::effective($this->idShop, $idLang);
        $this->featureNames = array();

        $tmp = $feed['file'] . '.tmp';
        $fh = @fopen($tmp, 'w');
        if (!$fh) {
            throw new Exception('No se puede escribir en la carpeta export/ del módulo (permisos).');
        }
        fwrite($fh, '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . "\n<channel>\n"
            . '<title>' . $this->x($this->shopName) . '</title>' . "\n"
            . '<link>' . $this->x($this->context->link->getPageLink('index', true, $idLang, null, false, $this->idShop)) . '</link>' . "\n"
            . '<description>' . $this->x($this->shopName . ' - feed de productos') . '</description>' . "\n");

        $exportCats = array();
        foreach ($this->categories as $idCat => $c) {
            if ($c['export']) {
                $exportCats[] = (int) $idCat;
            }
        }
        $db = Db::getInstance();
        if ($this->recordIssues) {
            $db->delete(self::ISSUES, 'id_shop = ' . $this->idShop);
        }

        if ($exportCats) {
            $lastId = 0;
            do {
                $ids = $db->executeS(
                    'SELECT ps.id_product FROM `' . _DB_PREFIX_ . 'product_shop` ps
                     WHERE ps.id_shop = ' . $this->idShop . ' AND ps.active = 1 AND ps.visibility <> "none"
                     AND ps.id_category_default IN (' . implode(',', $exportCats) . ')
                     AND ps.id_product > ' . (int) $lastId . '
                     ORDER BY ps.id_product ASC LIMIT ' . self::BATCH
                );
                foreach ((array) $ids as $row) {
                    $lastId = (int) $row['id_product'];
                    fwrite($fh, $this->productXml($lastId, $idLang));
                }
                $this->flushIssues();
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            } while (count((array) $ids) === self::BATCH);
        }

        fwrite($fh, "</channel>\n</rss>\n");
        fclose($fh);
        @chmod($tmp, 0644);
        if (!@rename($tmp, $feed['file'])) {
            @unlink($tmp);
            throw new Exception('No se pudo guardar el feed en la carpeta export/ (permisos).');
        }

        return $this->stats;
    }

    /** Escapa texto para XML */
    protected function x($text)
    {
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string) $text);

        return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    protected function tag($name, $value)
    {
        $value = (string) $value;
        if ($value === '') {
            return '';
        }

        return '<g:' . $name . '>' . $this->x($value) . '</g:' . $name . ">\n";
    }

    protected function exclude($code)
    {
        if (!isset($this->stats['excluded'][$code])) {
            $this->stats['excluded'][$code] = 0;
        }
        ++$this->stats['excluded'][$code];
    }

    protected function issue($idProduct, $idAttr, $code, $detail = '')
    {
        $types = self::issueTypes();
        if (isset($types[$code]) && $types[$code][0] === 1) {
            ++$this->stats['warnings'];
        }
        if ($this->recordIssues) {
            $this->issues[] = '(' . $this->idShop . ', ' . (int) $idProduct . ', ' . (int) $idAttr . ', "' . pSQL($code) . '", "' . pSQL(Tools::substr((string) $detail, 0, 250)) . '")';
        }
    }

    protected function flushIssues()
    {
        foreach (array_chunk($this->issues, 300) as $chunk) {
            Db::getInstance()->execute('INSERT IGNORE INTO `' . _DB_PREFIX_ . self::ISSUES . '` (id_shop, id_product, id_product_attribute, code, detail) VALUES ' . implode(',', $chunk));
        }
        $this->issues = array();
    }

    /** XML de un producto (una entrada, o una por combinación) */
    protected function productXml($idProduct, $idLang)
    {
        $p = new Product((int) $idProduct, false, $idLang, $this->idShop);
        if (!Validate::isLoadedObject($p)) {
            return '';
        }
        if (!$p->available_for_order) {
            $this->exclude('not_orderable');
            $this->issue($p->id, 0, 'not_orderable');

            return '';
        }

        $variants = array();
        if ($this->conf['JSS_COMBINATIONS'] && Combination::isFeatureActive() && $p->hasAttributes()) {
            $rows = $p->getAttributeCombinations($idLang);
            foreach ((array) $rows as $r) {
                $ipa = (int) $r['id_product_attribute'];
                if (!isset($variants[$ipa])) {
                    $variants[$ipa] = array('row' => $r, 'attributes' => array());
                }
                $variants[$ipa]['attributes'][(int) $r['id_attribute_group']] = array(
                    'group' => $r['group_name'],
                    'value' => $r['attribute_name'],
                );
            }
        }

        $xml = '';
        $features = null;
        ++$this->stats['products'];
        if ($variants) {
            foreach ($variants as $ipa => $v) {
                $xml .= $this->itemXml($p, $idLang, $ipa, $v, $features);
            }
        } else {
            $xml .= $this->itemXml($p, $idLang, 0, null, $features);
        }

        return $xml;
    }

    /** Valida un GTIN (8, 12, 13 o 14 cifras con dígito de control) */
    public static function validGtin($code)
    {
        $code = trim((string) $code);
        if (!preg_match('/^\d{8}$|^\d{12,14}$/', $code) || (int) ltrim($code, '0') === 0) {
            return false;
        }
        $digits = str_split(str_pad($code, 14, '0', STR_PAD_LEFT));
        $check = (int) array_pop($digits);
        $sum = 0;
        foreach ($digits as $i => $d) {
            $sum += (int) $d * ($i % 2 === 0 ? 3 : 1);
        }

        return (10 - ($sum % 10)) % 10 === $check;
    }

    protected function brandName($idManufacturer)
    {
        $id = (int) $idManufacturer;
        if (!$id) {
            return '';
        }
        if (!isset($this->brandCache[$id])) {
            $this->brandCache[$id] = (string) Manufacturer::getNameById($id);
        }

        return $this->brandCache[$id];
    }

    /** Valor de una característica del producto */
    protected function featureValues(Product $p, $idLang, &$features)
    {
        if ($features === null) {
            $features = array();
            $rows = Db::getInstance()->executeS(
                'SELECT fp.id_feature, fl.name, fvl.value FROM `' . _DB_PREFIX_ . 'feature_product` fp
                 JOIN `' . _DB_PREFIX_ . 'feature_lang` fl ON (fl.id_feature = fp.id_feature AND fl.id_lang = ' . (int) $idLang . ')
                 JOIN `' . _DB_PREFIX_ . 'feature_value_lang` fvl ON (fvl.id_feature_value = fp.id_feature_value AND fvl.id_lang = ' . (int) $idLang . ')
                 WHERE fp.id_product = ' . (int) $p->id . ' ORDER BY fl.name'
            );
            foreach ((array) $rows as $r) {
                $features[] = array('id' => (int) $r['id_feature'], 'name' => (string) $r['name'], 'value' => (string) $r['value']);
            }
        }

        return $features;
    }

    /** Valor de color/talla/material/estampado según lo configurado (a:grupo de atributos | f:característica) */
    protected function sourceValue($setting, $variant, array $features)
    {
        if (!preg_match('/^([af]):(\d+)$/', (string) $setting, $m)) {
            return '';
        }
        $id = (int) $m[2];
        if ($m[1] === 'a') {
            return $variant && isset($variant['attributes'][$id]) ? (string) $variant['attributes'][$id]['value'] : '';
        }
        $values = array();
        foreach ($features as $f) {
            if ($f['id'] === $id && $f['value'] !== '') {
                $values[] = $f['value'];
            }
        }

        return implode('/', $values);
    }

    protected function clean($html)
    {
        $text = str_replace(array('<br>', '<br/>', '<br />', '</p>', '</li>', '</div>', '</h2>', '</h3>'), "\n", (string) $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8');
        $text = preg_replace("/[ \t\xC2\xA0]+/u", ' ', $text);
        $text = preg_replace("/\s*\n\s*/", "\n", $text);

        return trim($text);
    }

    protected function cut($text, $max, &$cut = false)
    {
        $cut = false;
        if (Tools::strlen($text) <= $max) {
            return $text;
        }
        $cut = true;
        $text = Tools::substr($text, 0, $max);
        $space = strrpos($text, ' ');
        if ($space !== false && $space > $max * 0.7) {
            $text = substr($text, 0, $space);
        }

        return rtrim($text, " ,.;:-");
    }

    /** Una entrada del feed */
    protected function itemXml(Product $p, $idLang, $ipa, $variant, &$features)
    {
        $conf = $this->conf;
        $row = $variant ? $variant['row'] : null;
        $features = $this->featureValues($p, $idLang, $features);
        $cat = isset($this->categories[(int) $p->id_category_default]) ? $this->categories[(int) $p->id_category_default] : null;

        // Precio (en la moneda del feed: PrestaShop lo convierte con la moneda del contexto)
        $taxIncl = (bool) $conf['JSS_TAX_INCL'];
        $specific = null;
        $price = (float) Product::getPriceStatic($p->id, $taxIncl, $ipa, 6, null, false, true, 1, false, null, null, null, $specific);
        $regular = (float) Product::getPriceStatic($p->id, $taxIncl, $ipa, 6, null, false, false, 1);
        if ($regular <= 0) {
            $this->exclude('no_price');
            $this->issue($p->id, $ipa, 'no_price');

            return '';
        }
        if ((float) $conf['JSS_MIN_PRICE'] > 0 && $price < (float) $conf['JSS_MIN_PRICE']) {
            $this->exclude('min_price');

            return '';
        }

        // Disponibilidad
        $qty = (int) StockAvailable::getQuantityAvailableByProduct($p->id, $ipa, $this->idShop);
        $availabilityDate = '';
        if (!Configuration::get('PS_STOCK_MANAGEMENT') || $qty > 0) {
            $availability = 'in_stock';
        } elseif (Product::isAvailableWhenOutOfStock(StockAvailable::outOfStock($p->id, $this->idShop))) {
            // Se vende sin stock: Google lo llama «backorder» y exige la fecha en que estará
            $availability = 'backorder';
            $date = $row && !empty($row['available_date']) && $row['available_date'] !== '0000-00-00' ? $row['available_date'] : (string) $p->available_date;
            $ts = $date && $date !== '0000-00-00' ? strtotime($date . ' 12:00:00') : 0;
            if ($ts <= time()) {
                $ts = time() + 86400 * max(1, (int) $conf['JSS_BACKORDER_DAYS']);
            }
            $availabilityDate = date('Y-m-d\T12:00O', $ts);
        } else {
            $availability = 'out_of_stock';
            if (!$conf['JSS_OUT_OF_STOCK']) {
                $this->exclude('out_of_stock');

                return '';
            }
        }

        // Imágenes (las de la combinación; si no tiene, las del producto)
        $images = $ipa ? Image::getImages($idLang, $p->id, $ipa) : array();
        if (!$images) {
            $images = Image::getImages($idLang, $p->id);
        }
        if (!$images) {
            $this->exclude('no_image');
            $this->issue($p->id, $ipa, 'no_image');

            return '';
        }
        usort($images, function ($a, $b) {
            if ((int) $a['cover'] !== (int) $b['cover']) {
                return (int) $b['cover'] - (int) $a['cover'];
            }

            return (int) $a['position'] - (int) $b['position'];
        });
        $imgType = $conf['JSS_IMG_TYPE'];
        $imageUrls = array();
        foreach (array_slice($images, 0, self::MAX_IMAGES) as $im) {
            $url = $this->context->link->getImageLink($p->link_rewrite, $p->id . '-' . $im['id_image'], $imgType);
            if (strpos($url, '//') === 0) {
                $url = 'https:' . $url;
            } elseif (strpos($url, 'http') !== 0) {
                $url = Tools::getShopProtocol() . $url;
            }
            $imageUrls[] = $url;
        }

        // Identificadores
        $brand = $this->brandName($p->id_manufacturer);
        if ($brand === '' && $conf['JSS_BRAND_FALLBACK']) {
            $brand = trim((string) $conf['JSS_BRAND_NAME']) !== '' ? trim((string) $conf['JSS_BRAND_NAME']) : (string) $this->shopName;
        }
        if ($brand === '') {
            $this->issue($p->id, $ipa, 'no_brand');
        }
        $gtin = '';
        if ($conf['JSS_GTIN']) {
            $candidates = array();
            foreach (array('ean13', 'upc', 'isbn') as $field) {
                if ($row && !empty($row[$field])) {
                    $candidates[] = $row[$field];
                }
            }
            if (!$candidates) {
                foreach (array('ean13', 'upc', 'isbn') as $field) {
                    if (property_exists($p, $field) && !empty($p->{$field})) {
                        $candidates[] = $p->{$field};
                    }
                }
            }
            foreach ($candidates as $code) {
                $code = preg_replace('/[\s-]/', '', (string) $code);
                if (self::validGtin($code)) {
                    $gtin = $code;
                    break;
                }
            }
            if ($gtin === '' && $candidates) {
                $this->issue($p->id, $ipa, 'invalid_gtin', implode(', ', $candidates));
            }
        }
        $mpn = '';
        if ($conf['JSS_MPN'] === 'mpn') {
            if ($row && !empty($row['mpn'])) {
                $mpn = $row['mpn'];
            } elseif (property_exists($p, 'mpn') && !empty($p->mpn)) {
                $mpn = $p->mpn;
            }
        } elseif ($conf['JSS_MPN'] === 'supplier') {
            $mpn = $row && !empty($row['supplier_reference']) ? $row['supplier_reference'] : (string) $p->supplier_reference;
        }

        // Título y descripción
        $name = trim((string) $p->name);
        $title = $name;
        if ($variant) {
            $values = array();
            foreach ($variant['attributes'] as $a) {
                $values[] = $a['value'];
            }
            $title .= ' - ' . implode(' ', $values);
        }
        if ($conf['JSS_TITLE_BRAND'] && $brand !== '' && stripos($title, $brand) === false) {
            $title = $brand . ' ' . $title;
        }
        $wasCut = false;
        $title = $this->cut($this->clean($title), self::TITLE_MAX, $wasCut);
        if ($wasCut) {
            $this->issue($p->id, $ipa, 'title_cut');
        }
        $order = $conf['JSS_DESCRIPTION'] === 'short' ? array('description_short', 'description', 'meta_description')
            : ($conf['JSS_DESCRIPTION'] === 'meta' ? array('meta_description', 'description_short', 'description')
            : array('description', 'description_short', 'meta_description'));
        $description = '';
        foreach ($order as $field) {
            $description = $this->clean($p->{$field});
            if ($description !== '') {
                break;
            }
        }
        if ($description === '') {
            $description = $name;
            $this->issue($p->id, $ipa, 'no_description');
        }
        $description = $this->cut($description, self::DESCRIPTION_MAX);

        // 1.7: el décimo parámetro añade el ancla de la combinación; en 8 y 9 el ancla ya va por defecto
        $link = $this->context->link->getProductLink($p, null, null, null, $idLang, $this->idShop, $ipa, false, false, version_compare(_PS_VERSION_, '8.0.0', '<'));

        $id = $ipa ? $p->id . '-' . $ipa : (string) $p->id;
        $xml = "<item>\n";
        $xml .= $this->tag('id', $id);
        $xml .= $this->tag('title', $title);
        $xml .= $this->tag('description', $description);
        $xml .= $this->tag('link', $link);
        foreach ($imageUrls as $i => $url) {
            $xml .= $this->tag($i === 0 ? 'image_link' : 'additional_image_link', $url);
        }
        $xml .= $this->tag('availability', $availability);
        $xml .= $this->tag('availability_date', $availabilityDate);

        $iso = $this->currency->iso_code;
        if ($price < $regular - 0.004) {
            $xml .= $this->tag('price', number_format($regular, 2, '.', '') . ' ' . $iso);
            $xml .= $this->tag('sale_price', number_format($price, 2, '.', '') . ' ' . $iso);
            if (is_array($specific) && !empty($specific['to']) && $specific['to'] !== '0000-00-00 00:00:00') {
                $from = !empty($specific['from']) && $specific['from'] !== '0000-00-00 00:00:00' ? strtotime($specific['from']) : time();
                $xml .= $this->tag('sale_price_effective_date', date('Y-m-d\TH:iO', $from) . '/' . date('Y-m-d\TH:iO', strtotime($specific['to'])));
            }
        } else {
            $xml .= $this->tag('price', number_format($price, 2, '.', '') . ' ' . $iso);
        }

        $condition = in_array($p->condition, array('new', 'used', 'refurbished'), true) ? $p->condition : 'new';
        $xml .= $this->tag('condition', $condition);
        $xml .= $this->tag('brand', $brand);
        $xml .= $this->tag('gtin', $gtin);
        $xml .= $this->tag('mpn', $mpn);
        if ($gtin === '' && !($brand !== '' && $mpn !== '')) {
            $xml .= $this->tag('identifier_exists', 'no');
        }

        if ($cat && $cat['google_id']) {
            $xml .= $this->tag('google_product_category', (string) (int) $cat['google_id']);
        } elseif ($this->recordIssues) {
            $this->issue($p->id, $ipa, 'no_google_category');
        }
        if ($cat && $cat['path'] !== '') {
            $xml .= $this->tag('product_type', $this->cut($cat['path'], self::PRODUCT_TYPE_MAX));
        }
        if ($variant) {
            $xml .= $this->tag('item_group_id', (string) $p->id);
        }
        foreach (array('color' => 'JSS_COLOR', 'size' => 'JSS_SIZE', 'material' => 'JSS_MATERIAL', 'pattern' => 'JSS_PATTERN') as $tag => $key) {
            $xml .= $this->tag($tag, Tools::substr($this->sourceValue($conf[$key], $variant, $features), 0, 100));
        }
        if ($cat) {
            $xml .= $this->tag('gender', $cat['gender']);
            $xml .= $this->tag('age_group', $cat['age_group']);
        }

        // Envío
        $xml .= $this->shippingXml($p, $price, $ipa, $row);
        $weight = (float) $p->weight + ($row ? (float) $row['weight'] : 0);
        $unit = Tools::strtolower(trim((string) Configuration::get('PS_WEIGHT_UNIT')));
        if ($weight > 0 && in_array($unit, $this->isoUnitsWeight, true)) {
            $xml .= $this->tag('shipping_weight', number_format($weight, 3, '.', '') . ' ' . $unit);
        }

        // Características como detalles del producto
        if ($conf['JSS_PRODUCT_DETAIL']) {
            $used = array();
            foreach ($features as $f) {
                if ($f['value'] === '' || isset($used[$f['name'] . '|' . $f['value']]) || count($used) >= 30) {
                    continue;
                }
                $used[$f['name'] . '|' . $f['value']] = true;
                $xml .= '<g:product_detail>' . $this->tag('attribute_name', Tools::substr($f['name'], 0, 140))
                    . $this->tag('attribute_value', Tools::substr($f['value'], 0, 1000)) . "</g:product_detail>\n";
            }
        }

        // Etiquetas para separar campañas en Google Ads
        if ($conf['JSS_CUSTOM_LABELS']) {
            $xml .= $this->tag('custom_label_0', $price < $regular - 0.004 ? 'oferta' : 'precio normal');
            if ($cat && $cat['top'] !== '') {
                $xml .= $this->tag('custom_label_1', Tools::substr($cat['top'], 0, 100));
            }
            $xml .= $this->tag('custom_label_2', $availability === 'in_stock' ? 'con stock' : 'sin stock');
        }

        $xml .= "</item>\n";
        ++$this->stats['items'];

        return $xml;
    }

    /** Envío según lo configurado: nada (en Merchant Center), precio fijo o el transportista más barato */
    protected function shippingXml(Product $p, $price, $ipa, $row)
    {
        $mode = $this->conf['JSS_SHIPPING'];
        $iso = $this->currency->iso_code;
        if ($mode === 'fixed') {
            $amount = $p->is_virtual ? 0 : (float) Tools::convertPrice((float) $this->conf['JSS_SHIP_PRICE'], $this->currency);

            return '<g:shipping>' . $this->tag('country', $this->country->iso_code)
                . $this->tag('price', number_format($amount, 2, '.', '') . ' ' . $iso) . "</g:shipping>\n";
        }
        if ($mode !== 'carriers') {
            return '';
        }
        $xml = '';
        $weight = (float) $p->weight + ($row ? (float) $row['weight'] : 0);
        foreach (JssSettings::ids('JSS_SHIP_COUNTRIES') as $idCountry) {
            $country = new Country($idCountry);
            if (!Validate::isLoadedObject($country)) {
                continue;
            }
            $cost = $p->is_virtual ? 0.0 : $this->cheapestShipping($p, (int) $country->id_zone, $price, $weight);
            if ($cost === null) {
                continue;
            }
            $xml .= '<g:shipping>' . $this->tag('country', $country->iso_code)
                . $this->tag('price', number_format($cost, 2, '.', '') . ' ' . $iso) . "</g:shipping>\n";
        }

        return $xml;
    }

    /** Transportista más barato de la zona para este producto (null si ninguno lo lleva) */
    protected function cheapestShipping(Product $p, $idZone, $price, $weight)
    {
        if (!isset($this->carrierCache[$idZone])) {
            $this->carrierCache[$idZone] = array();
            foreach ((array) Carrier::getCarriers((int) $this->context->language->id, true, false, (int) $idZone, null, Carrier::ALL_CARRIERS) as $c) {
                $carrier = new Carrier((int) $c['id_carrier']);
                // Los de módulo con cálculo externo no se pueden calcular aquí
                if ($carrier->is_module && $carrier->shipping_external) {
                    continue;
                }
                $this->carrierCache[$idZone][] = $carrier;
            }
        }
        $allowed = array();
        foreach ((array) $p->getCarriers() as $c) {
            $allowed[] = (int) $c['id_reference'];
        }
        $freePrice = (float) Configuration::get('PS_SHIPPING_FREE_PRICE');
        $freeWeight = (float) Configuration::get('PS_SHIPPING_FREE_WEIGHT');
        $best = null;
        foreach ($this->carrierCache[$idZone] as $carrier) {
            if ($allowed && !in_array((int) $carrier->id_reference, $allowed, true)) {
                continue;
            }
            if ($carrier->max_weight > 0 && $weight > $carrier->max_weight) {
                continue;
            }
            if ($carrier->is_free || ($freePrice > 0 && $price >= $freePrice) || ($freeWeight > 0 && $weight >= $freeWeight)) {
                $cost = 0.0;
            } else {
                $cost = $carrier->getShippingMethod() == Carrier::SHIPPING_METHOD_WEIGHT
                    ? (float) $carrier->getDeliveryPriceByWeight($weight, $idZone)
                    : (float) $carrier->getDeliveryPriceByPrice($price, $idZone, (int) $this->currency->id);
                if ($carrier->shipping_handling) {
                    $cost += (float) Configuration::get('PS_SHIPPING_HANDLING');
                }
                $cost += (float) $p->additional_shipping_cost;
                if ($this->conf['JSS_TAX_INCL']) {
                    $cost *= 1 + ((float) Tax::getCarrierTaxRate((int) $carrier->id) / 100);
                }
                $cost = (float) Tools::convertPrice($cost, $this->currency);
            }
            if ($best === null || $cost < $best) {
                $best = $cost;
            }
        }

        return $best;
    }

    /** Problemas del diagnóstico: recuento por código */
    public static function issueCounts($idShop)
    {
        $out = array();
        $rows = Db::getInstance()->executeS('SELECT code, COUNT(*) n FROM `' . _DB_PREFIX_ . self::ISSUES . '` WHERE id_shop = ' . (int) $idShop . ' GROUP BY code');
        foreach ((array) $rows as $r) {
            $out[$r['code']] = (int) $r['n'];
        }

        return $out;
    }

    /** Productos con un problema (paginado) */
    public static function issueList($idShop, $code, $idLang, $page, $perPage)
    {
        $offset = max(0, ((int) $page - 1) * (int) $perPage);

        return (array) Db::getInstance()->executeS(
            'SELECT i.id_product, i.id_product_attribute, i.detail, pl.name, p.reference,
                    (SELECT GROUP_CONCAT(al.name ORDER BY al.id_attribute SEPARATOR " ")
                       FROM `' . _DB_PREFIX_ . 'product_attribute_combination` pac
                       JOIN `' . _DB_PREFIX_ . 'attribute_lang` al ON (al.id_attribute = pac.id_attribute AND al.id_lang = ' . (int) $idLang . ')
                      WHERE pac.id_product_attribute = i.id_product_attribute) AS combination
             FROM `' . _DB_PREFIX_ . self::ISSUES . '` i
             LEFT JOIN `' . _DB_PREFIX_ . 'product` p ON p.id_product = i.id_product
             LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON (pl.id_product = i.id_product AND pl.id_lang = ' . (int) $idLang . ' AND pl.id_shop = ' . (int) $idShop . ')
             WHERE i.id_shop = ' . (int) $idShop . ' AND i.code = "' . pSQL($code) . '"
             ORDER BY i.id_product, i.id_product_attribute
             LIMIT ' . (int) $offset . ', ' . (int) $perPage
        );
    }

    public static function deleteShop($idShop)
    {
        Db::getInstance()->delete(self::ISSUES, 'id_shop = ' . (int) $idShop);
        foreach (glob(dirname(__FILE__) . '/../export/feed_*_' . (int) $idShop . '_*.xml') ?: array() as $f) {
            @unlink($f);
        }
    }
}
