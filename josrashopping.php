<?php
/**
 * Josra Shopping Feed — feed de productos para Google Merchant Center
 *
 * Basado en gshoppingflux 1.8.3 de Dim00z
 * (Copyright 2014-2026 Google Shopping Flux Contributors, https://github.com/dim00z/gshoppingflux),
 * con licencia Apache License 2.0. Modificado y adaptado por josra (2026): asistente de primera
 * configuración, diagnóstico, categorías de Google con buscador, feed servido por controlador
 * y generación revisada según la especificación de datos de producto de Google.
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not use this file
 * except in compliance with the License. You may obtain a copy of the License at
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * @author    josra
 * @copyright 2014-2026 Google Shopping Flux Contributors
 * @copyright 2026 josra (adaptación)
 * @license   Apache License 2.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/classes/JosrashoppingUpdater.php';
require_once dirname(__FILE__) . '/classes/JssSettings.php';
require_once dirname(__FILE__) . '/classes/JssTaxonomy.php';
require_once dirname(__FILE__) . '/classes/JssCategories.php';
require_once dirname(__FILE__) . '/classes/JssFeed.php';
require_once dirname(__FILE__) . '/classes/JssAdmin.php';

class Josrashopping extends Module
{
    public function __construct()
    {
        $this->name = 'josrashopping';
        $this->tab = 'advertising_marketing';
        $this->version = '1.0.1';
        $this->author = 'josra';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = array('min' => '1.6.1.0', 'max' => _PS_VERSION_);

        parent::__construct();

        $this->displayName = $this->l('Josra Shopping Feed');
        $this->description = $this->l('Feed de productos para Google Merchant Center (Google Shopping), con asistente de configuración y diagnóstico.');
        $this->confirmUninstall = $this->l('¿Seguro? Se borrarán los ajustes, las categorías de Google asignadas y los feeds generados.');
    }

    public function getUpdater()
    {
        return new JosrashoppingUpdater($this);
    }

    /** Hooks del módulo (los del actualizador incluidos) */
    public function getHooks()
    {
        return array_merge(array('actionObjectCategoryDeleteAfter'), $this->getUpdater()->getHooks());
    }

    public function install()
    {
        if (!parent::install() || !$this->installDb()) {
            return false;
        }
        foreach ($this->getHooks() as $hook) {
            if (!$this->registerHook($hook)) {
                return false;
            }
        }
        JssSettings::ensureKeys();

        return true;
    }

    public function installDb()
    {
        return JssCategories::installTable() && JssFeed::installTable();
    }

    public function uninstall()
    {
        $this->getUpdater()->notifyUninstall();
        if (!parent::uninstall()) {
            return false;
        }
        Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . JssCategories::TABLE . '`');
        Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . JssFeed::ISSUES . '`');
        JssSettings::deleteAll();
        foreach (glob(dirname(__FILE__) . '/export/feed_*.xml') ?: array() as $file) {
            @unlink($file);
        }
        foreach (array('_UPD_TS', '_UPD_INFO', '_UPD_SU', '_UPD_LT') as $suffix) {
            Configuration::deleteByName('JOSRASHOPPING' . $suffix);
        }

        return true;
    }

    /** Autorreparación al abrir la configuración (por si se actualizó subiendo archivos) */
    public function repair()
    {
        $this->installDb();
        foreach ($this->getHooks() as $hook) {
            if (!$this->isRegisteredInHook($hook)) {
                $this->registerHook($hook);
            }
        }
        JssSettings::ensureKeys();
    }

    /** Aviso periódico al servidor de actualizaciones (solo en el back-office) */
    public function hookActionAdminControllerSetMedia($params)
    {
        $this->getUpdater()->maybePing();
    }

    /** PS 8.1+: la versión nueva aparece en Gestor de módulos > Actualizaciones */
    public function hookActionListModules($params)
    {
        return $this->getUpdater()->getNativeModuleList();
    }

    /** Al borrar una categoría, se borra su categoría de Google asignada */
    public function hookActionObjectCategoryDeleteAfter($params)
    {
        if (!empty($params['object']) && !empty($params['object']->id)) {
            Db::getInstance()->delete(JssCategories::TABLE, 'id_category = ' . (int) $params['object']->id);
        }
    }

    public function getContent()
    {
        $admin = new JssAdmin($this);

        return $admin->run();
    }
}
