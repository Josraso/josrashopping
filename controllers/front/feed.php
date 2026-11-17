<?php
/**
 * Josra Shopping Feed — URL del feed para Google Merchant Center
 *
 * Sirve el XML ya generado. Si tiene más horas de las configuradas, lo regenera: con PHP-FPM
 * después de enviar el actual (Google no espera); si no, antes de enviarlo.
 *
 * @author    josra
 * @license   Apache License 2.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/../../classes/JssSettings.php';
require_once dirname(__FILE__) . '/../../classes/JssCategories.php';
require_once dirname(__FILE__) . '/../../classes/JssFeed.php';

class JosrashoppingFeedModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $display_header = false;
    public $display_footer = false;

    /** Google tiene que poder leer el feed aunque la tienda esté en mantenimiento */
    protected function displayMaintenancePage()
    {
    }

    protected function displayRestrictedCountryPage()
    {
    }

    public function initContent()
    {
        if (!Module::isEnabled('josrashopping')) {
            $this->fail(404, 'El módulo está desactivado.');
        }
        $key = (string) JssSettings::get('JSS_FEED_KEY');
        if ($key === '' || !hash_equals($key, (string) Tools::getValue('k'))) {
            $this->fail(403, 'Clave del feed no válida.');
        }
        $idShop = (int) $this->context->shop->id;
        $feed = null;
        foreach (JssFeed::feeds($idShop) as $f) {
            if (Tools::strtolower($f['lang']) === Tools::strtolower((string) Tools::getValue('l'))
                && Tools::strtolower($f['currency']) === Tools::strtolower((string) Tools::getValue('c'))) {
                $feed = $f;
                break;
            }
        }
        if (!$feed) {
            $this->fail(404, 'Este feed no está configurado (idioma o moneda).');
        }

        $refresh = max(1, (int) JssSettings::get('JSS_REFRESH')) * 3600;
        $exists = is_file($feed['file']);
        $stale = !$exists || filemtime($feed['file']) < time() - $refresh;
        $generator = new JssFeed($this->module, $idShop);

        if (!$exists) {
            $generator->generateAll();
            clearstatcache();
            if (!is_file($feed['file'])) {
                $run = JssSettings::lastRun();
                $this->fail(500, 'No se pudo generar el feed. ' . (isset($run['error']) ? $run['error'] : ''));
            }
            $stale = false;
        } elseif ($stale && !function_exists('fastcgi_finish_request')) {
            $generator->generateAll();
            clearstatcache();
            $stale = false;
        }

        $this->send($feed['file']);
        if ($stale) {
            // Ya enviado: se regenera para la próxima lectura
            fastcgi_finish_request();
            $generator->generateAll();
        }
        exit;
    }

    protected function send($file)
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Length: ' . filesize($file));
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($file)) . ' GMT');
        header('X-Robots-Tag: noindex');
        readfile($file);
        flush();
    }

    protected function fail($code, $message)
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code($code);
        header('Content-Type: text/plain; charset=utf-8');
        echo $message;
        exit;
    }
}
