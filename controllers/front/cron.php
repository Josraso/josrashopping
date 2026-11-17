<?php
/**
 * Josra Shopping Feed — URL para regenerar los feeds desde una tarea programada (Plesk, cron…)
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

class JosrashoppingCronModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $display_header = false;
    public $display_footer = false;

    protected function displayMaintenancePage()
    {
    }

    protected function displayRestrictedCountryPage()
    {
    }

    public function initContent()
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: text/plain; charset=utf-8');
        if (!Module::isEnabled('josrashopping')) {
            http_response_code(404);
            exit('KO: el módulo está desactivado.');
        }
        $token = (string) JssSettings::get('JSS_CRON_TOKEN');
        if ($token === '' || !hash_equals($token, (string) Tools::getValue('token'))) {
            http_response_code(403);
            exit('KO: clave no válida.');
        }
        $generator = new JssFeed($this->module, (int) $this->context->shop->id);
        $run = $generator->generateAll();
        if (!empty($run['error'])) {
            http_response_code(500);
            exit('KO: ' . $run['error']);
        }
        $items = isset($run['stats']['items']) ? (int) $run['stats']['items'] : 0;
        exit('OK: ' . $items . ' artículos enviados en ' . $run['seconds'] . ' s.');
    }
}
