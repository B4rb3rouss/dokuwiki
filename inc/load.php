<?php

/**
 * Load all internal libraries and setup class autoloader
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 */

use dokuwiki\ErrorHandler;
use dokuwiki\Extension\PluginController;

// setup class autoloader
spl_autoload_register('load_autoload');

// require all the common libraries
// for a few of these order does matter
require_once(DOKU_INC . 'inc/defines.php');
require_once(DOKU_INC . 'inc/compatibility.php');   // load early so we can use it everywhere
require_once(DOKU_INC . 'inc/actions.php');
require_once(DOKU_INC . 'inc/changelog.php');
require_once(DOKU_INC . 'inc/common.php');
require_once(DOKU_INC . 'inc/confutils.php');
require_once(DOKU_INC . 'inc/pluginutils.php');
require_once(DOKU_INC . 'inc/form.php');
require_once(DOKU_INC . 'inc/fulltext.php');
require_once(DOKU_INC . 'inc/html.php');
require_once(DOKU_INC . 'inc/httputils.php');
require_once(DOKU_INC . 'inc/indexer.php');
require_once(DOKU_INC . 'inc/infoutils.php');
require_once(DOKU_INC . 'inc/io.php');
require_once(DOKU_INC . 'inc/mail.php');
require_once(DOKU_INC . 'inc/media.php');
require_once(DOKU_INC . 'inc/pageutils.php');
require_once(DOKU_INC . 'inc/parserutils.php');
require_once(DOKU_INC . 'inc/search.php');
require_once(DOKU_INC . 'inc/template.php');
require_once(DOKU_INC . 'inc/toolbar.php');
require_once(DOKU_INC . 'inc/utf8.php');
require_once(DOKU_INC . 'inc/auth.php');
require_once(DOKU_INC . 'inc/deprecated.php');
require_once(DOKU_INC . 'inc/legacy.php');

/**
 * spl_autoload_register callback
 *
 * Contains a static list of DokuWiki's core classes and automatically
 * require()s their associated php files when an object is instantiated.
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @todo   add generic loading of renderers and auth backends
 *
 * @param string $name
 *
 * @return bool
 */
function load_autoload($name)
{
    static $classes = null;
    if ($classes === null) $classes = [
        'Diff'                  => DOKU_INC . 'inc/DifferenceEngine.php',
        'UnifiedDiffFormatter'  => DOKU_INC . 'inc/DifferenceEngine.php',
        'TableDiffFormatter'    => DOKU_INC . 'inc/DifferenceEngine.php',
        'cache'                 => DOKU_INC . 'inc/cache.php',
        'cache_parser'          => DOKU_INC . 'inc/cache.php',
        'cache_instructions'    => DOKU_INC . 'inc/cache.php',
        'cache_renderer'        => DOKU_INC . 'inc/cache.php',
        'Input'                 => DOKU_INC . 'inc/Input.class.php',
        'JpegMeta'              => DOKU_INC . 'inc/JpegMeta.php',
        'SimplePie'             => DOKU_INC . 'inc/SimplePie.php',
        'FeedParser'            => DOKU_INC . 'inc/FeedParser.php',
        'SafeFN'                => DOKU_INC . 'inc/SafeFN.class.php',
        'Mailer'                => DOKU_INC . 'inc/Mailer.class.php',
        'Doku_Handler'          => DOKU_INC . 'inc/parser/handler.php',
        'Doku_Renderer'          => DOKU_INC . 'inc/parser/renderer.php',
        'Doku_Renderer_xhtml'    => DOKU_INC . 'inc/parser/xhtml.php',
        'Doku_Renderer_code'     => DOKU_INC . 'inc/parser/code.php',
        'Doku_Renderer_xhtmlsummary' => DOKU_INC . 'inc/parser/xhtmlsummary.php',
        'Doku_Renderer_metadata' => DOKU_INC . 'inc/parser/metadata.php'
    ];

    if (isset($classes[$name])) {
        require($classes[$name]);
        return true;
    }

    // namespace to directory conversion
    $name = str_replace('\\', '/', $name);

    // test mock namespace
    if (str_starts_with($name, 'dokuwiki/test/mock/')) {
        $file = DOKU_INC . '_test/mock/' . substr($name, 19) . '.php';
        if (file_exists($file)) {
            require $file;
            return true;
        }
    }

    // tests namespace
    if (str_starts_with($name, 'dokuwiki/test/')) {
        $file = DOKU_INC . '_test/tests/' . substr($name, 14) . '.php';
        if (file_exists($file)) {
            require $file;
            return true;
        }
    }

    // plugin namespace
    if (str_starts_with($name, 'dokuwiki/plugin/')) {
        $name = str_replace('/test/', '/_test/', $name); // no underscore in test namespace
        $file = DOKU_PLUGIN . substr($name, 16) . '.php';
        if (file_exists($file)) {
            try {
                require $file;
            } catch (\Throwable $e) {
                ErrorHandler::showExceptionMsg($e, "Error loading plugin $name");
            }
            return true;
        }
    }

    // template namespace
    if (str_starts_with($name, 'dokuwiki/template/')) {
        $name = str_replace('/test/', '/_test/', $name); // no underscore in test namespace
        $file = DOKU_INC . 'lib/tpl/' . substr($name, 18) . '.php';
        if (file_exists($file)) {
            try {
                require $file;
            } catch (\Throwable $e) {
                ErrorHandler::showExceptionMsg($e, "Error loading template $name");
            }
            return true;
        }
    }

    // our own namespace
    if (str_starts_with($name, 'dokuwiki/')) {
        $file = DOKU_INC . 'inc/' . substr($name, 9) . '.php';
        if (file_exists($file)) {
            require $file;
            return true;
        }
    }

    // Plugin loading
    if (
        preg_match(
            '/^(' . implode('|', PluginController::PLUGIN_TYPES) . ')_plugin_(' .
            DOKU_PLUGIN_NAME_REGEX .
            ')(?:_([^_]+))?$/',
            $name,
            $m
        )
    ) {
        // try to load the wanted plugin file
        $c = ((count($m) === 4) ? "/{$m[3]}" : '');
        $plg = DOKU_PLUGIN . "{$m[2]}/{$m[1]}$c.php";
        if (file_exists($plg)) {
            try {
                require $plg;
            } catch (\Throwable $e) {
                ErrorHandler::showExceptionMsg($e, "Error loading plugin {$m[2]}");
            }
        }
        return true;
    }
    return false;
}
