<?php
/**
 * Josra Shopping Feed — configuración en el back-office: asistente, resumen, diagnóstico,
 * ajustes y categorías. Todo dentro de Módulos > Configurar, con los componentes nativos.
 *
 * @author    josra
 * @license   Apache License 2.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class JssAdmin
{
    const STEPS = 6;
    const PER_PAGE = 50;

    /** @var Josrashopping */
    protected $module;
    protected $context;
    protected $idShop;
    protected $idLang;
    protected $html = '';

    public function __construct($module)
    {
        $this->module = $module;
        $this->context = Context::getContext();
        $this->idShop = (int) $this->context->shop->id;
        $this->idLang = (int) $this->context->language->id;
    }

    /* ==================== URLs ==================== */

    /** URL completa de la configuración (en PS 8/9 una relativa da 404) */
    public function baseUrl()
    {
        return Tools::getShopDomainSsl(true) . __PS_BASE_URI__ . basename(_PS_ADMIN_DIR_)
            . '/index.php?controller=AdminModules&configure=' . $this->module->name;
    }

    public function url(array $params = array())
    {
        $url = $this->baseUrl() . '&token=' . Tools::getAdminTokenLite('AdminModules');
        foreach ($params as $k => $v) {
            $url .= '&' . $k . '=' . urlencode((string) $v);
        }

        return $url;
    }

    protected function redirect(array $params)
    {
        Tools::redirectAdmin($this->url($params));
    }

    /** Enlace a la ficha del producto en el back-office */
    protected function productUrl($idProduct)
    {
        $id = (int) $idProduct;
        if (version_compare(_PS_VERSION_, '8.1.0', '>=')) {
            return $this->context->link->getAdminLink('AdminProducts', true, array('route' => 'admin_products_edit', 'productId' => $id));
        }
        if (version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
            return $this->context->link->getAdminLink('AdminProducts', true, array('id_product' => $id, 'updateproduct' => 1));
        }

        return $this->context->link->getAdminLink('AdminProducts') . '&id_product=' . $id . '&updateproduct';
    }

    /* ==================== entrada ==================== */

    public function run()
    {
        // AJAX: buscador de categorías de Google (antes de cualquier salida)
        if (Tools::getValue('jss_ajax') === 'taxonomy') {
            $this->ajaxTaxonomy();
        }

        $updater = $this->module->getUpdater();
        $output = $updater->processRequest();
        $this->module->repair();

        if (Shop::isFeatureActive() && Shop::getContext() != Shop::CONTEXT_SHOP) {
            return $output . $this->module->displayWarning('Elige una tienda concreta arriba (no «Todas las tiendas» ni un grupo): el feed se configura por tienda.') . $updater->renderPanel();
        }

        $this->context->controller->addJS($this->module->getPathUri() . 'views/js/admin.js?v=' . $this->module->version);
        $output .= $this->process();

        $wizard = !JssSettings::get('JSS_DONE') || Tools::getValue('jss_wizard');
        $body = $wizard ? $this->renderWizard() : $this->renderMain();

        $info = $updater->getInfo();
        $panel = $updater->renderPanel();

        return !empty($info['update_available']) ? $output . $panel . $body : $output . $body . $panel;
    }

    protected function ajaxTaxonomy()
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        die(json_encode(JssTaxonomy::search((string) Tools::getValue('q'), $this->context->language->iso_code, 30)));
    }

    /* ==================== acciones ==================== */

    protected function process()
    {
        $out = '';
        if (Tools::isSubmit('submitJssStep') || Tools::isSubmit('jss_back') || Tools::isSubmit('jss_skip')) {
            $step = (int) Tools::getValue('jss_step');
            $back = Tools::isSubmit('jss_back');
            $errors = Tools::isSubmit('jss_skip') ? array() : $this->saveStep($step);
            if ($errors && !$back) {
                return $this->module->displayError(implode('<br>', $errors));
            }
            $next = $back ? max(1, $step - 1) : min(self::STEPS, $step + 1);
            $params = array('jss_wizard' => 1, 'jss_step' => $next);
            if ($next === self::STEPS && !$back) {
                $params['jss_gen'] = 1;
            }
            $this->redirect($params);
        }
        if (Tools::isSubmit('submitJssFinish')) {
            JssSettings::set('JSS_DONE', 1);
            $this->redirect(array('jss_view' => 'summary', 'jss_msg' => 'done'));
        }
        if (Tools::isSubmit('submitJssConfig')) {
            $errors = $this->saveFields($this->configFields());
            if ($errors) {
                return $this->module->displayError(implode('<br>', $errors));
            }
            $this->redirect(array('jss_view' => 'config', 'jss_msg' => 'saved'));
        }
        if (Tools::isSubmit('submitJssCategories')) {
            $this->saveCategories();
            $this->redirect(array('jss_view' => 'categories', 'jss_msg' => 'saved'));
        }
        if (Tools::isSubmit('submitJssGenerate')) {
            $generator = new JssFeed($this->module, $this->idShop);
            $run = $generator->generateAll();
            $this->redirect(array('jss_view' => 'summary', 'jss_msg' => !empty($run['error']) ? 'generror' : 'generated'));
        }
        if (Tools::isSubmit('submitJssReset')) {
            JssFeed::deleteShop($this->idShop);
            JssCategories::deleteShop($this->idShop);
            JssSettings::resetShop();
            JssSettings::ensureKeys();
            $this->redirect(array('jss_wizard' => 1, 'jss_step' => 1, 'jss_msg' => 'reset'));
        }

        $messages = array(
            'saved' => array('ok', 'Cambios guardados. El feed se regenera con ellos la próxima vez que lo lea Google, o pulsa «Generar ahora».'),
            'generated' => array('ok', 'Feed generado.'),
            'done' => array('ok', 'Configuración terminada. Ya puedes dar de alta el feed en Merchant Center.'),
            'reset' => array('ok', 'Módulo reiniciado: se han borrado los ajustes, las categorías de Google y los feeds. Vuelve a configurarlo con el asistente.'),
        );
        $msg = (string) Tools::getValue('jss_msg');
        if ($msg === 'generror') {
            $run = JssSettings::lastRun();
            $out .= $this->module->displayError('No se pudo generar el feed: ' . Tools::safeOutput(isset($run['error']) ? $run['error'] : 'motivo desconocido'));
        } elseif (isset($messages[$msg])) {
            $out .= $this->module->displayConfirmation($messages[$msg][1]);
        }

        return $out;
    }

    /* ==================== campos ==================== */

    protected function languages()
    {
        return Language::getLanguages(true, $this->idShop);
    }

    protected function currencies()
    {
        $out = array();
        foreach ((array) Currency::getCurrenciesByIdShop($this->idShop) as $c) {
            if (!empty($c['deleted'])) {
                continue;
            }
            $out[] = array('id' => (int) $c['id_currency'], 'name' => $c['iso_code'] . (!empty($c['name']) ? ' - ' . $c['name'] : ''));
        }

        return $out;
    }

    protected function countries()
    {
        $out = array();
        foreach ((array) Country::getCountries($this->idLang, true) as $c) {
            $out[] = array('id' => (int) $c['id_country'], 'name' => $c['name'] . ' (' . $c['iso_code'] . ')');
        }

        return $out;
    }

    protected function imageTypeOptions()
    {
        $out = array();
        foreach (JssSettings::imageTypes() as $t) {
            $out[] = array('id' => $t['name'], 'name' => $t['name'] . ' (' . $t['width'] . '×' . $t['height'] . ')' . ($t['ok'] ? ' ✓' : ' — pequeña para Google'));
        }

        return $out;
    }

    protected function switchField($name, $label, $desc = '', $tab = null)
    {
        $field = array(
            'type' => 'switch',
            'label' => $label,
            'name' => $name,
            'is_bool' => true,
            'desc' => $desc,
            'values' => array(
                array('id' => $name . '_on', 'value' => 1, 'label' => 'Sí'),
                array('id' => $name . '_off', 'value' => 0, 'label' => 'No'),
            ),
        );
        if ($tab) {
            $field['tab'] = $tab;
        }

        return $field;
    }

    protected function select($name, $label, array $options, $desc = '', $tab = null, $multiple = false)
    {
        $field = array(
            'type' => 'select',
            'label' => $label,
            'name' => $multiple ? $name . '[]' : $name,
            'desc' => $desc,
            'options' => array('query' => $options, 'id' => 'id', 'name' => 'name'),
        );
        if ($multiple) {
            $field['multiple'] = true;
            $field['size'] = 8;
        }
        if ($tab) {
            $field['tab'] = $tab;
        }

        return $field;
    }

    protected function text($name, $label, $desc = '', $suffix = '', $tab = null)
    {
        $field = array('type' => 'text', 'label' => $label, 'name' => $name, 'desc' => $desc, 'class' => 'fixed-width-md');
        if ($suffix !== '') {
            $field['suffix'] = $suffix;
        }
        if ($tab) {
            $field['tab'] = $tab;
        }

        return $field;
    }

    /** Definición de cada campo: nombre del formulario => array(clave, tipo de valor) */
    protected function fieldMap()
    {
        return array(
            'jss_langs' => array('JSS_LANGS', 'langs'),
            'jss_currency' => array('JSS_CURRENCY', 'int'),
            'jss_country' => array('JSS_COUNTRY', 'int'),
            'jss_tax_incl' => array('JSS_TAX_INCL', 'bool'),
            'jss_refresh' => array('JSS_REFRESH', 'refresh'),
            'jss_combinations' => array('JSS_COMBINATIONS', 'bool'),
            'jss_out_of_stock' => array('JSS_OUT_OF_STOCK', 'bool'),
            'jss_backorder_days' => array('JSS_BACKORDER_DAYS', 'days'),
            'jss_min_price' => array('JSS_MIN_PRICE', 'price'),
            'jss_description' => array('JSS_DESCRIPTION', array('long', 'short', 'meta')),
            'jss_brand_fallback' => array('JSS_BRAND_FALLBACK', 'bool'),
            'jss_brand_name' => array('JSS_BRAND_NAME', 'string'),
            'jss_title_brand' => array('JSS_TITLE_BRAND', 'bool'),
            'jss_gtin' => array('JSS_GTIN', 'bool'),
            'jss_mpn' => array('JSS_MPN', array('mpn', 'supplier', 'none')),
            'jss_img_type' => array('JSS_IMG_TYPE', 'imgtype'),
            'jss_product_detail' => array('JSS_PRODUCT_DETAIL', 'bool'),
            'jss_custom_labels' => array('JSS_CUSTOM_LABELS', 'bool'),
            'jss_color' => array('JSS_COLOR', 'source'),
            'jss_size' => array('JSS_SIZE', 'source'),
            'jss_material' => array('JSS_MATERIAL', 'source'),
            'jss_pattern' => array('JSS_PATTERN', 'source'),
            'jss_shipping' => array('JSS_SHIPPING', array('account', 'fixed', 'carriers')),
            'jss_ship_price' => array('JSS_SHIP_PRICE', 'price'),
            'jss_ship_countries' => array('JSS_SHIP_COUNTRIES', 'ids'),
        );
    }

    /** Lee y guarda los campos indicados; devuelve los errores (en ese caso no guarda nada) */
    protected function saveFields(array $fields)
    {
        $map = $this->fieldMap();
        $values = array();
        $errors = array();
        foreach ($fields as $f) {
            $name = preg_replace('/\[\]$/', '', $f['name']);
            if (!isset($map[$name])) {
                continue;
            }
            list($key, $type) = $map[$name];
            if ($type === 'langs') {
                $ids = array();
                foreach ($this->languages() as $l) {
                    if (Tools::getValue('jss_langs_' . (int) $l['id_lang'])) {
                        $ids[] = (int) $l['id_lang'];
                    }
                }
                if (!$ids) {
                    $errors[] = 'Elige al menos un idioma para el feed.';
                }
                $values[$key] = implode(',', $ids);
            } elseif ($type === 'ids') {
                $values[$key] = implode(',', array_filter(array_map('intval', (array) Tools::getValue($name))));
            } elseif ($type === 'int') {
                $values[$key] = (int) Tools::getValue($name);
            } elseif ($type === 'bool') {
                $values[$key] = Tools::getValue($name) ? 1 : 0;
            } elseif ($type === 'price') {
                $values[$key] = max(0, (float) str_replace(',', '.', (string) Tools::getValue($name)));
            } elseif ($type === 'days') {
                $values[$key] = min(90, max(1, (int) Tools::getValue($name)));
            } elseif ($type === 'refresh') {
                $v = (int) Tools::getValue($name);
                $values[$key] = in_array($v, array(1, 3, 6, 12, 24), true) ? $v : 24;
            } elseif ($type === 'string') {
                $values[$key] = Tools::substr(trim(strip_tags((string) Tools::getValue($name))), 0, 70);
            } elseif ($type === 'source') {
                $v = (string) Tools::getValue($name);
                $values[$key] = preg_match('/^[af]:\d+$/', $v) ? $v : '';
            } elseif ($type === 'imgtype') {
                $v = (string) Tools::getValue($name);
                $ok = false;
                foreach (JssSettings::imageTypes() as $t) {
                    $ok = $ok || $t['name'] === $v;
                }
                $values[$key] = $ok ? $v : JssSettings::defaultImageType();
            } elseif (is_array($type)) {
                $v = (string) Tools::getValue($name);
                $values[$key] = in_array($v, $type, true) ? $v : $type[0];
            }
        }
        if (isset($values['JSS_SHIPPING']) && $values['JSS_SHIPPING'] === 'carriers' && isset($values['JSS_SHIP_COUNTRIES']) && $values['JSS_SHIP_COUNTRIES'] === '') {
            $errors[] = 'Para calcular el envío con tus transportistas, elige al menos un país.';
        }
        if ($errors) {
            return $errors;
        }
        foreach ($values as $key => $v) {
            JssSettings::set($key, $v);
        }

        return array();
    }

    /** Valores para rellenar los formularios */
    protected function fieldValues()
    {
        $conf = JssSettings::all();
        $values = array();
        foreach ($this->fieldMap() as $name => $def) {
            list($key, $type) = $def;
            if ($type === 'langs') {
                $selected = JssSettings::ids('JSS_LANGS');
                foreach ($this->languages() as $l) {
                    $values['jss_langs_' . (int) $l['id_lang']] = in_array((int) $l['id_lang'], $selected, true);
                }
            } elseif ($type === 'ids') {
                $values[$name . '[]'] = JssSettings::ids($key);
            } else {
                $values[$name] = $conf[$key];
            }
        }

        return $values;
    }

    /* ==================== formularios ==================== */

    protected function helper($submit, $tabs = null)
    {
        $helper = new HelperForm();
        $helper->module = $this->module;
        $helper->name_controller = $this->module->name;
        $helper->identifier = 'id_module';
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = $this->baseUrl();
        $helper->default_form_language = $this->idLang;
        $helper->allow_employee_form_lang = (int) Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $helper->languages = $this->context->controller->getLanguages();
        $helper->submit_action = $submit;
        $helper->show_toolbar = false;
        $helper->tpl_vars = array('fields_value' => $this->fieldValues());

        return $helper;
    }

    protected function shopFields($tab = null)
    {
        $langField = array(
            'type' => 'checkbox',
            'label' => 'Idiomas del feed',
            'name' => 'jss_langs',
            'desc' => 'Se crea un feed por idioma. Para vender en España basta con el español.',
            'values' => array('query' => $this->languages(), 'id' => 'id_lang', 'name' => 'name'),
        );
        if ($tab) {
            $langField['tab'] = $tab;
        }

        return array(
            $langField,
            $this->select('jss_currency', 'Moneda', $this->currencies(), 'La moneda en la que salen los precios del feed.', $tab),
            $this->select('jss_country', 'País de venta', $this->countries(), 'El país al que vendes con este feed. En Merchant Center tendrás que elegir el mismo.', $tab),
            $this->switchField('jss_tax_incl', 'Precios con IVA', 'En España y el resto de la UE Google exige los precios con IVA incluido. Déjalo en Sí.', $tab),
        );
    }

    protected function productFields($tab = null)
    {
        return array(
            $this->switchField('jss_combinations', 'Combinaciones como variantes', 'Cada combinación (talla, color…) sale como un artículo propio, agrupado con su producto. Recomendado.', $tab),
            $this->switchField('jss_out_of_stock', 'Incluir los agotados', 'Si se incluyen, Google los muestra como «agotado» y vuelven a salir solos al tener stock.', $tab),
            $this->text('jss_backorder_days', 'Plazo de los que se venden sin stock', 'Para productos que admiten pedidos sin stock: Google pide una fecha de disponibilidad. Se usa la fecha de la ficha si la tiene; si no, hoy más estos días.', 'días', $tab),
            $this->text('jss_min_price', 'Precio mínimo', 'No se envían los productos más baratos que esto. 0 = todos.', $this->currencySign(), $tab),
        );
    }

    protected function dataFields($tab = null)
    {
        $brandName = $this->text('jss_brand_name', 'Nombre de la marca propia', 'La marca que llevan los productos sin fabricante. Pon el nombre comercial, no la razón social.', '', $tab);
        $brandName['class'] = 'fixed-width-xxl';

        return array(
            $this->select('jss_description', 'Descripción', array(
                array('id' => 'long', 'name' => 'Descripción larga'),
                array('id' => 'short', 'name' => 'Resumen (descripción corta)'),
                array('id' => 'meta', 'name' => 'Meta descripción (SEO)'),
            ), 'Si la elegida está vacía se usa otra; si están todas vacías, el nombre.', $tab),
            $this->switchField('jss_brand_fallback', 'Marca propia', 'Productos sin fabricante: se envían con tu marca propia. Google pide la marca en los productos nuevos.', $tab),
            $brandName,
            $this->switchField('jss_title_brand', 'Marca delante del título', 'Ejemplo: «Shiro Casco MX-308». Ayuda en búsquedas por marca.', $tab),
            $this->switchField('jss_gtin', 'Enviar el EAN como GTIN', 'Se comprueba que el código sea válido; los que no lo son se quedan sin enviar y salen en el diagnóstico.', $tab),
            $this->select('jss_mpn', 'Referencia del fabricante (MPN)', array(
                array('id' => 'mpn', 'name' => 'Campo MPN de la ficha'),
                array('id' => 'supplier', 'name' => 'Referencia del proveedor'),
                array('id' => 'none', 'name' => 'No enviar'),
            ), 'Google quiere la referencia del fabricante, no la tuya interna.', $tab),
            $this->select('jss_img_type', 'Tamaño de imagen', $this->imageTypeOptions(), 'Desde el 31/01/2027 Google exige imágenes de 500×500 o más (marcadas con ✓).', $tab),
        );
    }

    protected function extraFields($tab = null)
    {
        $sources = JssSettings::sourceOptions($this->idLang);

        return array(
            $this->select('jss_color', 'Color', $sources, 'De dónde sale el color (obligatorio en ropa y calzado).', $tab),
            $this->select('jss_size', 'Talla', $sources, 'De dónde sale la talla (obligatoria en ropa y calzado).', $tab),
            $this->select('jss_material', 'Material', $sources, '', $tab),
            $this->select('jss_pattern', 'Estampado', $sources, '', $tab),
            $this->switchField('jss_product_detail', 'Enviar las características', 'Las características de la ficha van como «detalles del producto», que Google muestra en la ficha de Shopping.', $tab),
            $this->switchField('jss_custom_labels', 'Etiquetas para campañas', 'Añade etiquetas para separar campañas en Google Ads: 0 = oferta / precio normal, 1 = categoría principal, 2 = con stock / sin stock.', $tab),
        );
    }

    protected function shippingFields($tab = null)
    {
        $field = array(
            'type' => 'radio',
            'label' => 'Gastos de envío',
            'name' => 'jss_shipping',
            'class' => 't',
            'values' => array(
                array('id' => 'jss_shipping_account', 'value' => 'account', 'label' => 'Los configuro en Merchant Center (recomendado: lo más sencillo y fiable)'),
                array('id' => 'jss_shipping_fixed', 'value' => 'fixed', 'label' => 'Precio fijo para todos los productos'),
                array('id' => 'jss_shipping_carriers', 'value' => 'carriers', 'label' => 'Calcularlo con mis transportistas (el más barato para cada producto)'),
            ),
        );
        if ($tab) {
            $field['tab'] = $tab;
        }

        return array(
            $field,
            $this->text('jss_ship_price', 'Precio fijo de envío', 'Solo con «Precio fijo». Con IVA si los precios van con IVA.', $this->currencySign(), $tab),
            $this->select('jss_ship_countries', 'Países', $this->countries(), 'Solo con «Calcularlo con mis transportistas». Mantén pulsado Ctrl para elegir varios. Los transportistas de módulo con cálculo propio (MBE, etc.) no se pueden calcular aquí.', $tab, true),
        );
    }

    protected function configFields()
    {
        $refresh = $this->select('jss_refresh', 'Actualizar el feed cada', array(
            array('id' => 1, 'name' => '1 hora'),
            array('id' => 3, 'name' => '3 horas'),
            array('id' => 6, 'name' => '6 horas'),
            array('id' => 12, 'name' => '12 horas'),
            array('id' => 24, 'name' => '24 horas'),
        ), 'Cuando Google lee el feed y tiene más de este tiempo, se regenera solo. No hace falta tarea programada.', 'shop');

        return array_merge(
            $this->shopFields('shop'),
            array($refresh),
            $this->productFields('products'),
            $this->dataFields('data'),
            $this->extraFields('extra'),
            $this->shippingFields('shipping')
        );
    }

    /* ==================== asistente ==================== */

    protected function stepTitles()
    {
        return array(
            1 => 'Tu tienda',
            2 => 'Qué productos',
            3 => 'Datos del producto',
            4 => 'Categorías de Google',
            5 => 'Envío',
            6 => 'Listo',
        );
    }

    protected function saveStep($step)
    {
        switch ($step) {
            case 1:
                return $this->saveFields($this->shopFields());
            case 2:
                return $this->saveFields($this->productFields());
            case 3:
                return $this->saveFields($this->dataFields());
            case 4:
                $this->saveCategories();

                return array();
            case 5:
                return $this->saveFields($this->shippingFields());
        }

        return array();
    }

    protected function renderWizard()
    {
        $step = min(self::STEPS, max(1, (int) Tools::getValue('jss_step', 1)));
        $this->context->smarty->assign(array(
            'jss_steps' => $this->stepTitles(),
            'jss_step' => $step,
            'jss_done' => (bool) JssSettings::get('JSS_DONE'),
            'jss_step_urls' => $this->stepUrls($step),
            'jss_exit_url' => $this->url(array('jss_view' => 'summary')),
        ));
        $html = $this->fetch('wizard_header.tpl');

        if ($step === 4) {
            return $html . $this->renderCategories(true, $step);
        }
        if ($step === 6) {
            return $html . $this->renderFinish();
        }

        $intro = array(
            1 => 'Vamos a dejar listo el feed de productos para Google Merchant Center en unos minutos. Todo viene ya rellenado con lo que tiene tu tienda: si no sabes qué poner, deja lo que hay y pulsa «Siguiente».',
            2 => 'Elige qué productos se envían a Google. Solo se envían productos activos y visibles.',
            3 => 'Cómo se rellenan los datos de cada producto. Abajo tienes cómo está tu catálogo.',
            5 => 'Google necesita saber cuánto cuesta el envío. Lo más sencillo es configurarlo una vez en Merchant Center.',
        );
        $fields = $step === 1 ? $this->shopFields() : ($step === 2 ? $this->productFields() : ($step === 3 ? $this->dataFields() : $this->shippingFields()));
        $titles = $this->stepTitles();
        $form = array(
            'form' => array(
                'legend' => array('title' => $step . '. ' . $titles[$step], 'icon' => 'icon-magic'),
                'description' => $intro[$step],
                'input' => array_merge($fields, array(array('type' => 'hidden', 'name' => 'jss_step'))),
                'submit' => array('title' => 'Siguiente', 'icon' => 'icon-arrow-right', 'class' => 'btn btn-primary pull-right'),
            ),
        );
        if ($step > 1) {
            $form['form']['buttons'] = array(array(
                'title' => 'Atrás',
                'icon' => 'icon-arrow-left',
                'type' => 'submit',
                'name' => 'jss_back',
                'class' => 'btn btn-default pull-left',
            ));
        }
        $helper = $this->helper('submitJssStep');
        $helper->tpl_vars['fields_value']['jss_step'] = $step;
        $out = $helper->generateForm(array($form));
        if ($step === 3) {
            $out = $this->renderCatalogStats() . $out;
        }

        return $html . $out;
    }

    protected function stepUrls($current)
    {
        $out = array();
        for ($i = 1; $i <= self::STEPS; ++$i) {
            $out[$i] = ($i < $current || JssSettings::get('JSS_DONE')) ? $this->url(array('jss_wizard' => 1, 'jss_step' => $i)) : '';
        }

        return $out;
    }

    /** Cómo está el catálogo: productos con EAN válido, marca, imagen, descripción */
    protected function renderCatalogStats()
    {
        $db = Db::getInstance();
        $shop = (int) $this->idShop;
        $total = (int) $db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'product_shop` WHERE id_shop = ' . $shop . ' AND active = 1 AND visibility <> "none"');
        $rows = (array) $db->executeS(
            'SELECT p.ean13, p.upc, p.id_manufacturer,
                    (SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'image` i WHERE i.id_product = p.id_product) AS images,
                    LENGTH(pl.description) + LENGTH(pl.description_short) AS desc_len
             FROM `' . _DB_PREFIX_ . 'product_shop` ps
             JOIN `' . _DB_PREFIX_ . 'product` p ON p.id_product = ps.id_product
             LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON (pl.id_product = p.id_product AND pl.id_shop = ' . $shop . ' AND pl.id_lang = ' . (int) Configuration::get('PS_LANG_DEFAULT') . ')
             WHERE ps.id_shop = ' . $shop . ' AND ps.active = 1 AND ps.visibility <> "none"'
        );
        $stats = array('gtin' => 0, 'gtin_bad' => 0, 'brand' => 0, 'image' => 0, 'desc' => 0);
        foreach ($rows as $r) {
            $code = trim((string) $r['ean13']) !== '' ? $r['ean13'] : $r['upc'];
            if (trim((string) $code) !== '') {
                if (JssFeed::validGtin(preg_replace('/[\s-]/', '', $code))) {
                    ++$stats['gtin'];
                } else {
                    ++$stats['gtin_bad'];
                }
            }
            $stats['brand'] += (int) $r['id_manufacturer'] ? 1 : 0;
            $stats['image'] += (int) $r['images'] ? 1 : 0;
            $stats['desc'] += (int) $r['desc_len'] ? 1 : 0;
        }
        $pct = function ($n) use ($total) {
            return $total ? (int) round($n * 100 / $total) : 0;
        };
        $this->context->smarty->assign(array(
            'jss_total' => $total,
            'jss_stats' => array(
                array('label' => 'Con imagen', 'n' => $stats['image'], 'pct' => $pct($stats['image']), 'note' => 'Sin imagen no se pueden enviar.'),
                array('label' => 'Con fabricante (marca)', 'n' => $stats['brand'], 'pct' => $pct($stats['brand']), 'note' => 'Los demás pueden ir con «Marca propia».'),
                array('label' => 'Con EAN válido', 'n' => $stats['gtin'], 'pct' => $pct($stats['gtin']), 'note' => $stats['gtin_bad'] ? $stats['gtin_bad'] . ' tienen un EAN que no es válido.' : 'Sin EAN también se pueden enviar.'),
                array('label' => 'Con descripción', 'n' => $stats['desc'], 'pct' => $pct($stats['desc']), 'note' => 'Sin descripción se envía el nombre.'),
            ),
            'jss_img_ok' => JssSettings::imageTypeOk(JssSettings::get('JSS_IMG_TYPE')),
        ));

        return $this->fetch('catalog_stats.tpl');
    }

    protected function renderFinish()
    {
        if (Tools::getValue('jss_gen')) {
            $generator = new JssFeed($this->module, $this->idShop);
            $generator->generateAll();
        }
        $this->assignSummary();
        $this->context->smarty->assign(array(
            'jss_finish' => true,
            'jss_back_url' => $this->url(array('jss_wizard' => 1, 'jss_step' => 5)),
            'jss_regen_url' => $this->url(array('jss_wizard' => 1, 'jss_step' => 6, 'jss_gen' => 1)),
        ));

        return $this->fetch('finish.tpl');
    }

    /* ==================== pantalla principal ==================== */

    protected function renderMain()
    {
        $view = (string) Tools::getValue('jss_view', 'summary');
        if (!in_array($view, array('summary', 'diagnostics', 'config', 'categories'), true)) {
            $view = 'summary';
        }
        $counts = JssFeed::issueCounts($this->idShop);
        $this->context->smarty->assign(array(
            'jss_view' => $view,
            'jss_tabs' => array(
                'summary' => array('Resumen', 'icon-dashboard', $this->url(array('jss_view' => 'summary')), 0),
                'diagnostics' => array('Diagnóstico', 'icon-stethoscope', $this->url(array('jss_view' => 'diagnostics')), $this->problemCount($counts)),
                'config' => array('Configuración', 'icon-cogs', $this->url(array('jss_view' => 'config')), 0),
                'categories' => array('Categorías de Google', 'icon-sitemap', $this->url(array('jss_view' => 'categories')), 0),
            ),
        ));
        $html = $this->fetch('main_tabs.tpl');
        if ($view === 'diagnostics') {
            return $html . $this->renderDiagnostics($counts);
        }
        if ($view === 'config') {
            return $html . $this->renderConfig();
        }
        if ($view === 'categories') {
            return $html . $this->renderCategories(false, 0);
        }
        $this->assignSummary();

        return $html . $this->fetch('summary.tpl');
    }

    protected function assignSummary()
    {
        $feeds = array();
        foreach (JssFeed::feeds($this->idShop) as $f) {
            $exists = is_file($f['file']);
            $feeds[] = array(
                'lang' => Tools::strtoupper($f['lang']),
                'lang_name' => $f['lang_name'],
                'currency' => $f['currency'],
                'url' => $f['url'],
                'date' => $exists ? date('d/m/Y H:i', filemtime($f['file'])) : '',
                'size' => $exists ? number_format(filesize($f['file']) / 1048576, 2, ',', '.') . ' MB' : '',
            );
        }
        $run = JssSettings::lastRun();
        $stats = isset($run['stats']) ? $run['stats'] : array();
        $excluded = array();
        $labels = array(
            'out_of_stock' => 'Agotados (no se envían por tu ajuste)',
            'min_price' => 'Por debajo del precio mínimo',
            'no_image' => 'Sin imagen',
            'no_price' => 'Sin precio',
            'not_orderable' => 'No disponibles para pedidos',
        );
        foreach (isset($stats['excluded']) ? $stats['excluded'] : array() as $code => $n) {
            $excluded[] = array('label' => isset($labels[$code]) ? $labels[$code] : $code, 'n' => (int) $n);
        }
        $cron = $this->context->link->getModuleLink($this->module->name, 'cron', array('token' => JssSettings::get('JSS_CRON_TOKEN')), true, null, $this->idShop);
        $country = new Country((int) JssSettings::get('JSS_COUNTRY'), $this->idLang);
        $this->context->smarty->assign(array(
            'jss_feeds' => $feeds,
            'jss_run' => $run,
            'jss_run_date' => !empty($run['time']) ? date('d/m/Y H:i', (int) $run['time']) : '',
            'jss_items' => isset($stats['items']) ? (int) $stats['items'] : 0,
            'jss_products' => isset($stats['products']) ? (int) $stats['products'] : 0,
            'jss_warnings' => isset($stats['warnings']) ? (int) $stats['warnings'] : 0,
            'jss_excluded' => $excluded,
            'jss_issue_total' => $this->problemCount(JssFeed::issueCounts($this->idShop)),
            'jss_refresh' => (int) JssSettings::get('JSS_REFRESH'),
            'jss_cron' => $cron,
            'jss_country_name' => Validate::isLoadedObject($country) ? $country->name : '',
            'jss_shipping' => JssSettings::get('JSS_SHIPPING'),
            'jss_form_url' => $this->url(array('jss_view' => 'summary')),
            'jss_diag_url' => $this->url(array('jss_view' => 'diagnostics')),
            'jss_img_ok' => JssSettings::imageTypeOk(JssSettings::get('JSS_IMG_TYPE')),
            'jss_finish' => false,
        ));
    }

    protected function renderConfig()
    {
        $form = array(
            'form' => array(
                'legend' => array('title' => 'Configuración', 'icon' => 'icon-cogs'),
                'tabs' => array(
                    'shop' => 'Tienda y feed',
                    'products' => 'Productos',
                    'data' => 'Datos del producto',
                    'extra' => 'Moda y extras',
                    'shipping' => 'Envío',
                ),
                'input' => $this->configFields(),
                'submit' => array('title' => 'Guardar', 'icon' => 'icon-save'),
            ),
        );
        $helper = $this->helper('submitJssConfig');
        $out = $helper->generateForm(array($form));
        $this->context->smarty->assign(array(
            'jss_form_url' => $this->url(array('jss_view' => 'config')),
            'jss_wizard_url' => $this->url(array('jss_wizard' => 1, 'jss_step' => 1)),
        ));

        return $out . $this->fetch('maintenance.tpl');
    }

    protected function renderDiagnostics(array $counts)
    {
        $types = JssFeed::issueTypes();
        $code = (string) Tools::getValue('jss_code');
        if (!isset($types[$code])) {
            $code = '';
        }
        $page = max(1, (int) Tools::getValue('jss_page', 1));
        $rows = array();
        $pages = 1;
        if ($code !== '') {
            foreach (JssFeed::issueList($this->idShop, $code, $this->idLang, $page, self::PER_PAGE) as $r) {
                $rows[] = array(
                    'id' => (int) $r['id_product'],
                    'name' => (string) $r['name'],
                    'reference' => (string) $r['reference'],
                    'combination' => (string) $r['combination'],
                    'detail' => (string) $r['detail'],
                    'edit' => $this->productUrl($r['id_product']),
                );
            }
            $pages = max(1, (int) ceil((isset($counts[$code]) ? $counts[$code] : 0) / self::PER_PAGE));
        }
        $list = array();
        foreach ($types as $c => $t) {
            $list[] = array(
                'code' => $c,
                'level' => $t[0],
                'label' => $t[1],
                'help' => $t[2],
                'n' => isset($counts[$c]) ? $counts[$c] : 0,
                'url' => $this->url(array('jss_view' => 'diagnostics', 'jss_code' => $c)),
            );
        }
        $pageUrls = array();
        for ($i = 1; $i <= $pages; ++$i) {
            $pageUrls[$i] = $this->url(array('jss_view' => 'diagnostics', 'jss_code' => $code, 'jss_page' => $i));
        }
        $run = JssSettings::lastRun();
        $this->context->smarty->assign(array(
            'jss_types' => $list,
            'jss_code' => $code,
            'jss_code_label' => $code !== '' ? $types[$code][1] : '',
            'jss_code_help' => $code !== '' ? $types[$code][2] : '',
            'jss_rows' => $rows,
            'jss_page' => $page,
            'jss_page_urls' => $pageUrls,
            'jss_run_date' => !empty($run['time']) ? date('d/m/Y H:i', (int) $run['time']) : '',
            'jss_form_url' => $this->url(array('jss_view' => 'summary')),
        ));

        return $this->fetch('diagnostics.tpl');
    }

    /* ==================== categorías ==================== */

    protected function renderCategories($wizard, $step)
    {
        $iso = $this->context->language->iso_code;
        $rows = JssCategories::rows($this->idShop);
        $effective = JssCategories::effective($this->idShop, $this->idLang);
        $list = array();
        $tree = JssCategories::tree($this->idShop, $this->idLang);
        $hasChildren = array();
        foreach ($tree as $c) {
            $hasChildren[$c['parent']] = true;
        }
        foreach ($tree as $c) {
            $own = isset($rows[$c['id']]) ? $rows[$c['id']] : null;
            $eff = isset($effective[$c['id']]) ? $effective[$c['id']] : null;
            $list[] = array(
                'id' => $c['id'],
                'name' => $c['name'],
                'depth' => $c['depth'],
                'parent' => $c['parent'],
                'children' => isset($hasChildren[$c['id']]),
                'products' => $c['products'],
                'google' => $own && (int) $own['google_id'] ? JssTaxonomy::label((int) $own['google_id'], $iso) : '',
                'inherited' => (!$own || !(int) $own['google_id']) && $eff && $eff['google_id'] ? JssTaxonomy::label($eff['google_id'], $iso) : '',
                'export' => $own && $own['export'] !== null ? (string) (int) $own['export'] : '',
                'export_eff' => $eff ? (int) $eff['export'] : 1,
                'gender' => $own ? $own['gender'] : '',
                'age_group' => $own ? $own['age_group'] : '',
            );
        }
        $this->context->smarty->assign(array(
            'jss_cats' => $list,
            'jss_wizard' => $wizard,
            'jss_step' => $step,
            'jss_form_url' => $wizard ? $this->url(array('jss_wizard' => 1, 'jss_step' => $step)) : $this->url(array('jss_view' => 'categories')),
            'jss_ajax_url' => $this->url(array('jss_ajax' => 'taxonomy')),
            'jss_all_url' => $this->url(array('jss_view' => 'categories')),
            'jss_tax_help' => 'https://support.google.com/merchants/answer/6324436?hl=es',
            'jss_tax_file' => 'https://www.google.com/basepages/producttype/taxonomy-with-ids.es-ES.txt',
            'jss_genders' => array('' => '—', 'male' => 'Hombre', 'female' => 'Mujer', 'unisex' => 'Unisex'),
            'jss_ages' => array('' => '—', 'adult' => 'Adulto', 'kids' => 'Niños', 'toddler' => 'Niños pequeños', 'infant' => 'Bebés', 'newborn' => 'Recién nacidos'),
        ));

        return $this->fetch('categories.tpl');
    }

    protected function saveCategories()
    {
        $google = (array) Tools::getValue('jss_gcat');
        $export = (array) Tools::getValue('jss_export');
        $gender = (array) Tools::getValue('jss_gender');
        $age = (array) Tools::getValue('jss_age');
        $validGender = array('', 'male', 'female', 'unisex');
        $validAge = array('', 'adult', 'kids', 'toddler', 'infant', 'newborn');
        $known = array();
        foreach (JssCategories::tree($this->idShop, $this->idLang) as $c) {
            $known[$c['id']] = true;
        }
        foreach ($google as $idCat => $value) {
            $idCat = (int) $idCat;
            if (!isset($known[$idCat])) {
                continue;
            }
            $gid = JssTaxonomy::parse($value);
            if ($gid && JssTaxonomy::path($gid, 'en') === '') {
                $gid = 0;
            }
            $exp = isset($export[$idCat]) ? (string) $export[$idCat] : '';
            $exp = in_array($exp, array('0', '1'), true) ? $exp : '';
            $g = isset($gender[$idCat]) && in_array($gender[$idCat], $validGender, true) ? $gender[$idCat] : '';
            $a = isset($age[$idCat]) && in_array($age[$idCat], $validAge, true) ? $age[$idCat] : '';
            JssCategories::save($idCat, $this->idShop, $gid, $exp, $g, $a);
        }
    }

    /* ==================== utilidades ==================== */

    /** Productos con problemas (sin contar las sugerencias) */
    protected function problemCount(array $counts)
    {
        $types = JssFeed::issueTypes();
        $n = 0;
        foreach ($counts as $code => $c) {
            if (isset($types[$code]) && $types[$code][0] > 0) {
                $n += $c;
            }
        }

        return $n;
    }

    protected function currencySign()
    {
        $currency = new Currency((int) JssSettings::get('JSS_CURRENCY'));
        if (!Validate::isLoadedObject($currency)) {
            $currency = $this->context->currency;
        }

        return !empty($currency->sign) ? $currency->sign : $currency->iso_code;
    }

    protected function fetch($template)
    {
        $this->context->smarty->assign(array(
            'jss_module_version' => $this->module->version,
            'jss_tpl_dir' => _PS_MODULE_DIR_ . $this->module->name . '/views/templates/admin/',
        ));

        return $this->context->smarty->fetch(_PS_MODULE_DIR_ . $this->module->name . '/views/templates/admin/' . $template);
    }
}
