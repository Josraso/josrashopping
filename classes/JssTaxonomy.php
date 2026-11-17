<?php
/**
 * Josra Shopping Feed — taxonomía oficial de categorías de Google (va dentro del módulo, en data/)
 *
 * @author    josra
 * @license   Apache License 2.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class JssTaxonomy
{
    protected static $cache = array();

    /** Archivo de la taxonomía en el idioma pedido (español si existe, si no inglés) */
    protected static function file($iso)
    {
        $dir = dirname(__FILE__) . '/../data/';
        $iso = Tools::strtolower((string) $iso);
        if (in_array($iso, array('es', 'ca', 'gl', 'eu'), true) && is_file($dir . 'taxonomy-es-ES.txt')) {
            return $dir . 'taxonomy-es-ES.txt';
        }

        return $dir . 'taxonomy-en-US.txt';
    }

    /** Todas las categorías: id => ruta */
    public static function all($iso)
    {
        $file = self::file($iso);
        if (isset(self::$cache[$file])) {
            return self::$cache[$file];
        }
        $out = array();
        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ((array) $lines as $line) {
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $pos = strpos($line, ' - ');
            if ($pos === false) {
                continue;
            }
            $out[(int) substr($line, 0, $pos)] = trim(substr($line, $pos + 3));
        }
        self::$cache[$file] = $out;

        return $out;
    }

    public static function path($id, $iso)
    {
        $all = self::all($iso);

        return isset($all[(int) $id]) ? $all[(int) $id] : '';
    }

    /** «5181 - Casa y jardín > …» para mostrar en el campo */
    public static function label($id, $iso)
    {
        $id = (int) $id;
        if (!$id) {
            return '';
        }
        $path = self::path($id, $iso);

        return $id . ($path !== '' ? ' - ' . $path : '');
    }

    /** Sin acentos y en minúsculas, para buscar */
    protected static function fold($text)
    {
        $text = Tools::strtolower((string) $text);
        $from = array('á', 'à', 'ä', 'â', 'é', 'è', 'ë', 'ê', 'í', 'ì', 'ï', 'î', 'ó', 'ò', 'ö', 'ô', 'ú', 'ù', 'ü', 'û', 'ñ', 'ç');
        $to = array('a', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'n', 'c');

        return str_replace($from, $to, $text);
    }

    /**
     * Busca categorías que contengan todas las palabras (sin importar acentos).
     * Primero las que tienen la palabra en la última parte de la ruta.
     *
     * @return array lista de array('id', 'label')
     */
    public static function search($query, $iso, $limit = 30)
    {
        $query = trim((string) $query);
        if ($query === '') {
            return array();
        }
        if (ctype_digit($query)) {
            $path = self::path((int) $query, $iso);

            return $path !== '' ? array(array('id' => (int) $query, 'label' => self::label((int) $query, $iso))) : array();
        }
        $words = array_filter(preg_split('/\s+/', self::fold($query)));
        $best = array();
        $rest = array();
        foreach (self::all($iso) as $id => $path) {
            $folded = self::fold($path);
            $ok = true;
            foreach ($words as $w) {
                if (strpos($folded, $w) === false) {
                    $ok = false;
                    break;
                }
            }
            if (!$ok) {
                continue;
            }
            $parts = explode('>', $folded);
            $last = trim(end($parts));
            $inLast = true;
            foreach ($words as $w) {
                if (strpos($last, $w) === false) {
                    $inLast = false;
                    break;
                }
            }
            $row = array('id' => $id, 'label' => $id . ' - ' . $path);
            if ($inLast) {
                $best[] = $row;
            } else {
                $rest[] = $row;
            }
        }

        // Las más generales primero (menos niveles)
        $byDepth = function ($a, $b) {
            $da = substr_count($a['label'], '>');
            $db = substr_count($b['label'], '>');

            return $da === $db ? strcmp($a['label'], $b['label']) : $da - $db;
        };
        usort($best, $byDepth);
        usort($rest, $byDepth);

        return array_slice(array_merge($best, $rest), 0, $limit);
    }

    /** Saca el ID de lo escrito en el campo («5181 - Casa…» o «5181») */
    public static function parse($value)
    {
        if (preg_match('/^\s*(\d+)/', (string) $value, $m)) {
            return (int) $m[1];
        }

        return 0;
    }
}
