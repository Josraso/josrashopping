<?php
/**
 * Josra Shopping Feed — ajustes del módulo (por tienda)
 *
 * Basado en gshoppingflux (Copyright 2014-2026 Google Shopping Flux Contributors), Apache License 2.0.
 * Modificado por josra.
 *
 * @author    josra
 * @license   Apache License 2.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class JssSettings
{
    /**
     * Ajustes y su valor por defecto. Los que dependen de la tienda (idioma, moneda, país,
     * tipo de imagen) se calculan en defaults().
     */
    public static function keys()
    {
        return array(
            'JSS_DONE' => '0',              // asistente terminado
            'JSS_LANGS' => '',              // idiomas del feed (ids separados por comas)
            'JSS_CURRENCY' => '',           // moneda del feed (id)
            'JSS_COUNTRY' => '',            // país de venta (id)
            'JSS_TAX_INCL' => '1',          // precios con IVA
            'JSS_COMBINATIONS' => '1',      // cada combinación como variante
            'JSS_OUT_OF_STOCK' => '1',      // enviar también los agotados
            'JSS_BACKORDER_DAYS' => '7',    // días de plazo de los que se venden sin stock
            'JSS_MIN_PRICE' => '0',         // precio mínimo para enviar
            'JSS_DESCRIPTION' => 'long',    // long | short | meta
            'JSS_TITLE_BRAND' => '0',       // poner la marca delante del título
            'JSS_BRAND_FALLBACK' => '1',    // sin fabricante: se usa la marca propia
            'JSS_BRAND_NAME' => '',         // marca propia (por defecto, el nombre de la tienda)
            'JSS_GTIN' => '1',              // enviar EAN/UPC/ISBN como GTIN
            'JSS_MPN' => 'mpn',             // mpn | supplier | none
            'JSS_IMG_TYPE' => '',           // tipo de imagen
            'JSS_SHIPPING' => 'account',    // account | fixed | carriers
            'JSS_SHIP_PRICE' => '0',        // precio de envío fijo
            'JSS_SHIP_COUNTRIES' => '',     // países para calcular con transportistas (ids)
            'JSS_COLOR' => '',              // a:<id grupo de atributos> | f:<id característica>
            'JSS_SIZE' => '',
            'JSS_MATERIAL' => '',
            'JSS_PATTERN' => '',
            'JSS_PRODUCT_DETAIL' => '1',    // características como product_detail
            'JSS_CUSTOM_LABELS' => '1',     // etiquetas personalizadas (oferta, categoría)
            'JSS_REFRESH' => '24',          // horas entre regeneraciones
            'JSS_FEED_KEY' => '',           // clave de las URLs de los feeds
            'JSS_CRON_TOKEN' => '',         // clave de la URL de regeneración
            'JSS_LAST_RUN' => '',           // resumen de la última generación (json en base64)
        );
    }

    /** Valores por defecto que dependen de la tienda */
    public static function defaults()
    {
        $values = self::keys();
        $values['JSS_LANGS'] = (string) (int) Configuration::get('PS_LANG_DEFAULT');
        $values['JSS_CURRENCY'] = (string) (int) Configuration::get('PS_CURRENCY_DEFAULT');
        $values['JSS_COUNTRY'] = (string) (int) Configuration::get('PS_COUNTRY_DEFAULT');
        $values['JSS_SHIP_COUNTRIES'] = $values['JSS_COUNTRY'];
        $values['JSS_IMG_TYPE'] = self::defaultImageType();
        $values['JSS_BRAND_NAME'] = (string) Configuration::get('PS_SHOP_NAME');
        $values['JSS_COLOR'] = self::guessGroup(array('color', 'colour', 'colores', 'couleur', 'cor'));
        $values['JSS_SIZE'] = self::guessGroup(array('talla', 'size', 'tallas', 'taille', 'tamanho', 'número', 'numero'));

        return $values;
    }

    public static function get($key)
    {
        $value = Configuration::get($key);
        if ($value === false || $value === null) {
            $defaults = self::defaults();

            return isset($defaults[$key]) ? (string) $defaults[$key] : '';
        }

        return (string) $value;
    }

    /** Todos los ajustes de la tienda actual, completos (lo guardado sobre los valores por defecto) */
    public static function all()
    {
        $out = array();
        foreach (array_keys(self::keys()) as $key) {
            $out[$key] = self::get($key);
        }

        return $out;
    }

    public static function set($key, $value)
    {
        return Configuration::updateValue($key, (string) $value);
    }

    /** Lista de enteros a partir de un valor guardado con comas */
    public static function ids($key)
    {
        return array_values(array_filter(array_map('intval', explode(',', self::get($key)))));
    }

    /** Claves de las URLs: se crean si faltan */
    public static function ensureKeys()
    {
        foreach (array('JSS_FEED_KEY', 'JSS_CRON_TOKEN') as $key) {
            if (!Configuration::get($key)) {
                Configuration::updateValue($key, Tools::strtolower(Tools::passwdGen(24, 'ALPHANUMERIC')));
            }
        }
    }

    /** Borra los ajustes de la tienda actual (reiniciar). Las claves de las URLs se conservan. */
    public static function resetShop()
    {
        foreach (array_keys(self::keys()) as $key) {
            if ($key === 'JSS_FEED_KEY' || $key === 'JSS_CRON_TOKEN') {
                continue;
            }
            if (Shop::isFeatureActive() && Shop::getContext() == Shop::CONTEXT_SHOP) {
                Configuration::deleteFromContext($key);
            } else {
                Configuration::deleteByName($key);
            }
        }
    }

    /** Borra los ajustes de todas las tiendas (desinstalar) */
    public static function deleteAll()
    {
        foreach (array_keys(self::keys()) as $key) {
            Configuration::deleteByName($key);
        }
    }

    /** Tipos de imagen de producto: los de 500 px o más primero */
    public static function imageTypes()
    {
        $types = array();
        foreach ((array) ImageType::getImagesTypes('products') as $t) {
            $types[] = array(
                'name' => $t['name'],
                'width' => (int) $t['width'],
                'height' => (int) $t['height'],
                'ok' => (int) $t['width'] >= 500 && (int) $t['height'] >= 500,
            );
        }

        return $types;
    }

    /** Tipo de imagen por defecto: el más pequeño que llegue a 500×500; si no hay, el más grande */
    public static function defaultImageType()
    {
        $best = null;
        $largest = null;
        foreach (self::imageTypes() as $t) {
            if ($t['ok'] && ($best === null || $t['width'] < $best['width'])) {
                $best = $t;
            }
            if ($largest === null || $t['width'] > $largest['width']) {
                $largest = $t;
            }
        }
        if ($best) {
            return $best['name'];
        }

        return $largest ? $largest['name'] : '';
    }

    public static function imageTypeOk($name)
    {
        foreach (self::imageTypes() as $t) {
            if ($t['name'] === $name) {
                return $t['ok'];
            }
        }

        return false;
    }

    /** Busca un grupo de atributos por su nombre (color, talla...) para proponerlo */
    protected static function guessGroup(array $names)
    {
        $idLang = (int) Configuration::get('PS_LANG_DEFAULT');
        $rows = Db::getInstance()->executeS(
            'SELECT agl.id_attribute_group, agl.name, agl.public_name FROM `' . _DB_PREFIX_ . 'attribute_group_lang` agl
             WHERE agl.id_lang = ' . $idLang . ' ORDER BY agl.id_attribute_group'
        );
        foreach ((array) $rows as $r) {
            $label = Tools::strtolower(trim($r['name'] . ' ' . $r['public_name']));
            foreach ($names as $n) {
                if (strpos($label, $n) !== false) {
                    return 'a:' . (int) $r['id_attribute_group'];
                }
            }
        }

        return '';
    }

    /** Opciones para color/talla/material/estampado: grupos de atributos y características */
    public static function sourceOptions($idLang)
    {
        $options = array(array('id' => '', 'name' => '— No enviar —'));
        $groups = Db::getInstance()->executeS(
            'SELECT id_attribute_group, name FROM `' . _DB_PREFIX_ . 'attribute_group_lang` WHERE id_lang = ' . (int) $idLang . ' ORDER BY name'
        );
        foreach ((array) $groups as $g) {
            $options[] = array('id' => 'a:' . (int) $g['id_attribute_group'], 'name' => 'Combinación: ' . $g['name']);
        }
        $features = Db::getInstance()->executeS(
            'SELECT id_feature, name FROM `' . _DB_PREFIX_ . 'feature_lang` WHERE id_lang = ' . (int) $idLang . ' ORDER BY name'
        );
        foreach ((array) $features as $f) {
            $options[] = array('id' => 'f:' . (int) $f['id_feature'], 'name' => 'Característica: ' . $f['name']);
        }

        return $options;
    }

    /** Resumen de la última generación */
    public static function lastRun()
    {
        $raw = self::get('JSS_LAST_RUN');
        $data = $raw !== '' ? json_decode(base64_decode($raw), true) : null;

        return is_array($data) ? $data : array();
    }

    public static function saveLastRun(array $data)
    {
        return self::set('JSS_LAST_RUN', base64_encode(json_encode($data)));
    }
}
