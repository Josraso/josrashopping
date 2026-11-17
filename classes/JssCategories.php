<?php
/**
 * Josra Shopping Feed — categorías de la tienda y su categoría de Google
 *
 * Cada categoría guarda (si se indica) su categoría de Google, si se envía y, para moda,
 * el género y la edad. Lo que no se indica se hereda de la categoría superior.
 *
 * @author    josra
 * @license   Apache License 2.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class JssCategories
{
    const TABLE = 'josrashopping_category';

    public static function installTable()
    {
        return Db::getInstance()->execute('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::TABLE . '` (
            `id_category` int(10) unsigned NOT NULL,
            `id_shop` int(10) unsigned NOT NULL,
            `google_id` int(10) unsigned NOT NULL DEFAULT 0,
            `export` tinyint(1) NULL DEFAULT NULL,
            `gender` varchar(16) NOT NULL DEFAULT "",
            `age_group` varchar(16) NOT NULL DEFAULT "",
            PRIMARY KEY (`id_category`, `id_shop`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8');
    }

    /** Lo guardado para una tienda: id_category => fila */
    public static function rows($idShop)
    {
        $out = array();
        $rows = Db::getInstance()->executeS('SELECT * FROM `' . _DB_PREFIX_ . self::TABLE . '` WHERE id_shop = ' . (int) $idShop);
        foreach ((array) $rows as $r) {
            $out[(int) $r['id_category']] = $r;
        }

        return $out;
    }

    /** Guarda una categoría; si no queda nada indicado, se borra la fila (hereda) */
    public static function save($idCategory, $idShop, $googleId, $export, $gender, $ageGroup)
    {
        $db = Db::getInstance();
        $where = 'id_category = ' . (int) $idCategory . ' AND id_shop = ' . (int) $idShop;
        $exportSql = ($export === '' || $export === null) ? 'NULL' : (int) (bool) $export;
        if (!(int) $googleId && $exportSql === 'NULL' && $gender === '' && $ageGroup === '') {
            return $db->delete(self::TABLE, $where);
        }

        return $db->execute(
            'REPLACE INTO `' . _DB_PREFIX_ . self::TABLE . '` (id_category, id_shop, google_id, export, gender, age_group) VALUES ('
            . (int) $idCategory . ', ' . (int) $idShop . ', ' . (int) $googleId . ', ' . $exportSql . ', "'
            . pSQL($gender) . '", "' . pSQL($ageGroup) . '")'
        );
    }

    public static function deleteShop($idShop)
    {
        return Db::getInstance()->delete(self::TABLE, 'id_shop = ' . (int) $idShop);
    }

    /** Categoría de inicio de la tienda (la que cuelga de la raíz) */
    public static function homeId($idShop)
    {
        $shop = new Shop((int) $idShop);

        return (int) $shop->id_category ?: (int) Configuration::get('PS_HOME_CATEGORY');
    }

    /**
     * Árbol de categorías de la tienda, en orden y con su nivel (desde la de inicio, sin incluirla)
     *
     * @return array lista de array('id', 'name', 'depth', 'parent', 'products')
     */
    public static function tree($idShop, $idLang, $maxDepth = 0)
    {
        $home = self::homeId($idShop);
        $db = Db::getInstance();
        $homeRow = $db->getRow('SELECT nleft, nright, level_depth FROM `' . _DB_PREFIX_ . 'category` WHERE id_category = ' . (int) $home);
        if (!$homeRow) {
            return array();
        }
        $rows = $db->executeS(
            'SELECT c.id_category, c.id_parent, c.level_depth, cl.name,
                (SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'product_shop` ps
                  WHERE ps.id_category_default = c.id_category AND ps.id_shop = ' . (int) $idShop . ' AND ps.active = 1) AS products
             FROM `' . _DB_PREFIX_ . 'category` c
             JOIN `' . _DB_PREFIX_ . 'category_shop` cs ON (cs.id_category = c.id_category AND cs.id_shop = ' . (int) $idShop . ')
             LEFT JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON (cl.id_category = c.id_category AND cl.id_lang = ' . (int) $idLang . ' AND cl.id_shop = ' . (int) $idShop . ')
             WHERE c.nleft > ' . (int) $homeRow['nleft'] . ' AND c.nright < ' . (int) $homeRow['nright']
            . ($maxDepth ? ' AND c.level_depth <= ' . ((int) $homeRow['level_depth'] + (int) $maxDepth) : '') . '
             ORDER BY c.nleft'
        );
        $out = array();
        $index = array();
        foreach ((array) $rows as $r) {
            $index[(int) $r['id_category']] = count($out);
            $out[] = array(
                'id' => (int) $r['id_category'],
                'parent' => (int) $r['id_parent'],
                'name' => (string) $r['name'],
                'depth' => (int) $r['level_depth'] - (int) $homeRow['level_depth'],
                'products' => (int) $r['products'],
            );
        }
        // Productos de la categoría y de todas sus subcategorías (de abajo arriba)
        for ($i = count($out) - 1; $i >= 0; --$i) {
            $parent = $out[$i]['parent'];
            if (isset($index[$parent])) {
                $out[$index[$parent]]['products'] += $out[$i]['products'];
            }
        }

        return $out;
    }

    /**
     * Valores efectivos de todas las categorías (lo suyo o lo heredado de las superiores):
     * id_category => array('google_id', 'export', 'gender', 'age_group', 'path', 'top')
     */
    public static function effective($idShop, $idLang)
    {
        $rows = self::rows($idShop);
        $home = self::homeId($idShop);
        $out = array();
        // En orden nleft: el padre siempre se resuelve antes que el hijo
        foreach (self::tree($idShop, $idLang) as $c) {
            $parent = isset($out[$c['parent']]) ? $out[$c['parent']] : array(
                'google_id' => 0, 'export' => 1, 'gender' => '', 'age_group' => '', 'path' => '', 'top' => '',
            );
            $own = isset($rows[$c['id']]) ? $rows[$c['id']] : null;
            $out[$c['id']] = array(
                'google_id' => $own && (int) $own['google_id'] ? (int) $own['google_id'] : $parent['google_id'],
                'export' => $own && $own['export'] !== null ? (int) $own['export'] : $parent['export'],
                'gender' => $own && $own['gender'] !== '' ? $own['gender'] : $parent['gender'],
                'age_group' => $own && $own['age_group'] !== '' ? $own['age_group'] : $parent['age_group'],
                'path' => ($parent['path'] !== '' ? $parent['path'] . ' > ' : '') . $c['name'],
                'top' => $c['parent'] == $home ? $c['name'] : $parent['top'],
            );
        }

        return $out;
    }
}
