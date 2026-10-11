<?php

/**
 * @file MathJaxRenderPlugin.php
 *
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class MathJaxRenderPlugin
 *
 * @brief MathJax for OJS — renders LaTeX/TeX mathematics on journal pages by
 *        injecting a MathJax configuration and the MathJax library script.
 */

namespace APP\plugins\generic\mathJaxRender;

use APP\core\Application;
use APP\template\TemplateManager;
use PKP\config\Config;
use PKP\core\JSONMessage;
use PKP\core\PKPApplication;
use PKP\core\PKPPageRouter;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;

class MathJaxRenderPlugin extends GenericPlugin
{
    /** Pinned MathJax version. Never track "latest". */
    public const MATHJAX_VERSION = '3.2.2';

    /** Class marking subtrees MathJax must leave alone (editing surfaces). */
    public const IGNORE_CLASS = 'mathJaxRender_ignore';

    /** Site areas MathJax can be enabled for, with their default state. */
    public const AREA_DEFAULTS = [
        'areaArticle' => true,
        'areaGalley' => true,
        'areaIssue' => true,
        'areaSearch' => true,
        'areaHomepage' => false,
        'areaBackend' => false,
    ];

    /** @var bool Whether an HTML galley output capture is in progress */
    public $galleyBufferActive = false;

    /**
     * @copydoc Plugin::register()
     */
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) {
            return $success;
        }
        if ($success && $this->getEnabled($mainContextId)) {
            Hook::add('TemplateManager::display', [$this, 'injectMathJax']);
            // The HTML galley wrapper page has its own minimal template that
            // never calls {load_script}, so registered scripts cannot reach it;
            // its footer hook is the only insertion point.
            Hook::add('Templates::Common::Footer::PageFooter', [$this, 'injectGalleyWrapperFooter']);
            // HTML galley documents are echoed directly (not templated); these
            // two hooks bracket that echo, allowing an output-buffer capture.
            Hook::add('HtmlArticleGalleyPlugin::articleDownload', [$this, 'startGalleyOutputBuffer']);
            Hook::add('HtmlArticleGalleyPlugin::articleDownloadFinished', [$this, 'finishGalleyOutputBuffer']);
        }
        return $success;
    }

    /**
     * @copydoc Plugin::getDisplayName()
     */
    public function getDisplayName()
    {
        return __('plugins.generic.mathJaxRender.displayName');
    }

    /**
     * @copydoc Plugin::getDescription()
     */
    public function getDescription()
    {
        return __('plugins.generic.mathJaxRender.description');
    }

    /**
     * @copydoc Plugin::getActions()
     */
    public function getActions($request, $verb)
    {
        $router = $request->getRouter();
        return array_merge(
            $this->getEnabled() ? [
                new LinkAction(
                    'settings',
                    new AjaxModal(
                        $router->url($request, null, null, 'manage', null, ['verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic']),
                        $this->getDisplayName()
                    ),
                    __('manager.plugins.settings'),
                    null
                ),
            ] : [],
            parent::getActions($request, $verb)
        );
    }

    /**
     * @copydoc Plugin::manage()
     */
    public function manage($args, $request)
    {
        switch ($request->getUserVar('verb')) {
            case 'settings':
                $context = $request->getContext();

                $form = new MathJaxRenderSettingsForm($this, $context ? $context->getId() : PKPApplication::CONTEXT_ID_NONE);

                if ($request->getUserVar('save')) {
                    $form->readInputData();
                    if ($form->validate()) {
                        $form->execute();
                        return new JSONMessage(true);
                    }
                } else {
                    $form->initData();
                }
                return new JSONMessage(true, $form->fetch($request));
        }
        return parent::manage($args, $request);
    }

    /**
     * Read a plugin setting, falling back to its default when it has never
     * been saved (getSetting returns null in that case).
     *
     * @param int $contextId
     * @param string $name
     * @param mixed $default
     *
     * @return mixed
     */
    public function getSettingWithDefault($contextId, $name, $default)
    {
        $value = $this->getSetting($contextId, $name);
        return $value === null ? $default : $value;
    }

    /**
     * Determine which configurable site area the current request belongs to.
     *
     * @param \PKP\core\PKPRequest $request
     *
     * @return string|null One of the AREA_DEFAULTS keys, or null when the
     *  page is not covered by any configurable frontend area.
     */
    public function getRequestedArea($request)
    {
        $router = $request->getRouter();
        $page = $request->getRequestedPage();
        $op = $request->getRequestedOp();

        switch ($page) {
            case 'article':
                if ($op != 'view') {
                    return null;
                }
                // article/view/<id> is the landing page; article/view/<id>/<galleyId>
                // is the galley (full-text) wrapper page.
                $args = $router->getRequestedArgs($request);
                return count($args) >= 2 ? 'areaGalley' : 'areaArticle';
            case 'issue':
                return 'areaIssue';
            case 'search':
                return 'areaSearch';
            case '':
            case 'index':
            case 'announcement':
                return 'areaHomepage';
        }
        return null;
    }

    /**
     * Register the MathJax configuration and library scripts for enabled
     * frontend areas and (experimentally) for the editorial backend. The
     * configuration MUST be output before the MathJax library (MathJax v3
     * reads `window.MathJax` at load time), which is guaranteed by giving the
     * configuration a lower priority value.
     *
     * @param string $hookName `TemplateManager::display`
     * @param array $args [&$templateMgr, &$template, &$output]
     *
     * @return bool Hook processing continues
     */
    public function injectMathJax($hookName, $args)
    {
        $templateMgr = $args[0];
        $request = Application::get()->getRequest();

        // Never inject into non-page operations (e.g. component/API requests).
        $router = $request->getRouter();
        if (!$router instanceof PKPPageRouter) {
            return false;
        }

        // Settings are stored per journal; skip site-level pages.
        $context = $request->getContext();
        if (!$context) {
            return false;
        }
        $contextId = $context->getId();

        // Backend (editorial UI) rendering — experimental. Scripts registered
        // with the 'backend' context are only ever printed by backend layouts,
        // so no page detection is needed here.
        if ($this->getSettingWithDefault($contextId, 'areaBackend', self::AREA_DEFAULTS['areaBackend'])) {
            $templateMgr->addJavaScript(
                'mathJaxRenderBackendConfig',
                $this->getMathJaxConfig($contextId, true),
                [
                    'inline' => true,
                    'contexts' => 'backend',
                    'priority' => TemplateManager::STYLE_SEQUENCE_CORE,
                ]
            );
            $templateMgr->addJavaScript(
                'mathJaxRenderLib',
                $this->getMathJaxUrl($request, $contextId),
                [
                    'contexts' => 'backend',
                    'priority' => TemplateManager::STYLE_SEQUENCE_NORMAL,
                ]
            );
            $templateMgr->addJavaScript(
                'mathJaxRenderObserver',
                // Versioned by the plugin, not by OJS: an upgraded plugin must
                // never be served a browser-cached copy of the old observer.
                $request->getBaseUrl() . '/' . $this->getPluginPath() . '/js/backend-observer.js?v=' . $this->getPluginVersionString(),
                [
                    'contexts' => 'backend',
                    'priority' => TemplateManager::STYLE_SEQUENCE_LATE,
                ]
            );
        }

        // Frontend areas.
        $area = $this->getRequestedArea($request);
        if ($area === null) {
            return false;
        }
        if (!$this->getSettingWithDefault($contextId, $area, self::AREA_DEFAULTS[$area])) {
            return false;
        }

        // The HTML galley wrapper page is served by its own template, which is
        // where injectGalleyWrapperFooter() puts the scripts. Registering them
        // here as well would double-load MathJax under themes that override
        // that template and print registered scripts themselves.
        if ($area === 'areaGalley') {
            return false;
        }

        $templateMgr->addJavaScript(
            'mathJaxRenderConfig',
            $this->getMathJaxConfig($contextId),
            [
                'inline' => true,
                'contexts' => 'frontend',
                'priority' => TemplateManager::STYLE_SEQUENCE_CORE,
            ]
        );
        $templateMgr->addJavaScript(
            'mathJaxRenderLib',
            $this->getMathJaxUrl($request, $contextId),
            [
                'contexts' => 'frontend',
                'priority' => TemplateManager::STYLE_SEQUENCE_NORMAL,
            ]
        );
        // Typeset content that the page inserts after it has loaded, such as
        // the citation formats of the "How to cite" box.
        $templateMgr->addJavaScript(
            'mathJaxRenderFrontendObserver',
            $request->getBaseUrl() . '/' . $this->getPluginPath() . '/js/frontend-observer.js?v=' . $this->getPluginVersionString(),
            [
                'contexts' => 'frontend',
                'priority' => TemplateManager::STYLE_SEQUENCE_LATE,
            ]
        );
        // Core stylesheets hide aria-hidden elements inside the citation box,
        // which would also hide the formulas MathJax draws there.
        $templateMgr->addStyleSheet(
            'mathJaxRenderFrontend',
            'mjx-container[jax="CHTML"] > mjx-math[aria-hidden="true"] { display: inline-block !important; }',
            [
                'inline' => true,
                'contexts' => 'frontend',
            ]
        );
        return false;
    }

    /**
     * Print the MathJax scripts in the footer of the HTML galley wrapper page,
     * whose template does not call {load_script}. The wrapper shows the article
     * title, which may itself contain mathematics. Regular pages are skipped
     * here — they receive the scripts through addJavaScript().
     *
     * @param string $hookName `Templates::Common::Footer::PageFooter`
     * @param array $args [&$params, $smarty, &$output]
     *
     * @return bool Hook processing continues
     */
    public function injectGalleyWrapperFooter($hookName, $args)
    {
        $request = Application::get()->getRequest();
        $router = $request->getRouter();
        if (!$router instanceof PKPPageRouter) {
            return false;
        }

        $context = $request->getContext();
        if (!$context) {
            return false;
        }
        $contextId = $context->getId();

        if ($this->getRequestedArea($request) !== 'areaGalley') {
            return false;
        }
        if (!$this->getSettingWithDefault($contextId, 'areaGalley', self::AREA_DEFAULTS['areaGalley'])) {
            return false;
        }

        $args[2] .= '<script>' . $this->getMathJaxConfig($contextId) . '</script>'
            . '<script src="' . htmlspecialchars($this->getMathJaxUrl($request, $contextId), ENT_QUOTES) . '"></script>';
        return false;
    }

    /**
     * Start capturing the HTML galley document that
     * HtmlArticleGalleyPlugin::articleDownloadCallback() is about to echo.
     *
     * @param string $hookName `HtmlArticleGalleyPlugin::articleDownload`
     * @param array $args [$article, &$galley, &$fileId]
     *
     * @return bool false so the galley plugin proceeds with its output
     */
    public function startGalleyOutputBuffer($hookName, $args)
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        if (!$context) {
            return false;
        }
        if (!$this->getSettingWithDefault($context->getId(), 'areaGalley', self::AREA_DEFAULTS['areaGalley'])) {
            return false;
        }

        $this->galleyBufferActive = true;
        ob_start();
        return false;
    }

    /**
     * Inject the MathJax scripts into the captured HTML galley document,
     * before </body> when present (appended otherwise), and release it.
     *
     * @param string $hookName `HtmlArticleGalleyPlugin::articleDownloadFinished`
     * @param array $args [&$returner]
     *
     * @return bool Hook processing continues
     */
    public function finishGalleyOutputBuffer($hookName, $args)
    {
        if (!$this->galleyBufferActive) {
            return false;
        }
        $this->galleyBufferActive = false;

        $html = ob_get_clean();
        $request = Application::get()->getRequest();
        $contextId = $request->getContext()->getId();

        $scripts = '<script>' . $this->getMathJaxConfig($contextId) . '</script>'
            . '<script src="' . htmlspecialchars($this->getMathJaxUrl($request, $contextId), ENT_QUOTES) . '"></script>';

        // Insert before </body> without regex replacement: backslash sequences
        // in the config (TeX delimiters) must survive verbatim.
        $pos = stripos($html, '</body>');
        if ($pos !== false) {
            $html = substr_replace($html, $scripts, $pos, 0);
        } else {
            $html .= $scripts;
        }

        echo $html;
        return false;
    }

    /**
     * Pick the MathJax combined component for the current input settings.
     *
     * @param int $contextId
     *
     * @return string Filename within the es5 directory
     */
    public function getMathJaxComponent($contextId)
    {
        if ($this->getSettingWithDefault($contextId, 'inputMathml', false)
            || $this->getSettingWithDefault($contextId, 'inputAsciimath', false)) {
            return 'tex-mml-chtml.js';
        }
        return 'tex-chtml.js';
    }

    /**
     * The installed plugin version, used to bust browser caches for the
     * plugin's own assets. Falls back to the MathJax version when the plugin
     * has not been registered in the versions table (development checkouts).
     *
     * @return string
     */
    public function getPluginVersionString()
    {
        $version = $this->getCurrentVersion();
        return $version ? $version->getVersionString() : self::MATHJAX_VERSION;
    }

    /**
     * Absolute filesystem path of the bundled MathJax base component, used
     * to detect whether the self-hosted copy ships with this installation.
     *
     * @return string
     */
    public function getBundledLibPath()
    {
        return dirname(__FILE__) . '/mathjax/es5/tex-chtml.js';
    }

    /**
     * Whether the bundled (self-hosted) copy is configured but its files are
     * missing on disk, i.e. the plugin is falling back to the CDN.
     *
     * @param int $contextId
     *
     * @return bool
     */
    public function isBundledFallbackActive($contextId)
    {
        return $this->getSettingWithDefault($contextId, 'source', 'bundled') == 'bundled'
            && !file_exists($this->getBundledLibPath());
    }

    /**
     * Build the URL of the MathJax library for the configured source.
     * When the bundled copy is selected but its files are missing on disk,
     * fall back to the CDN so formulas never break — and make that fallback
     * visible (settings-form warning + error log) since an administrator who
     * chose self-hosting for privacy reasons must be able to notice it.
     *
     * @param \PKP\core\PKPRequest $request
     * @param int $contextId
     *
     * @return string
     */
    public function getMathJaxUrl($request, $contextId)
    {
        static $fallbackLogged = false;
        $component = $this->getMathJaxComponent($contextId);
        if ($this->getSettingWithDefault($contextId, 'source', 'bundled') == 'bundled') {
            if (file_exists(dirname(__FILE__) . '/mathjax/es5/' . $component)) {
                return $request->getBaseUrl() . '/' . $this->getPluginPath() . '/mathjax/es5/' . $component;
            }
            if (!$fallbackLogged) {
                $fallbackLogged = true;
                error_log('MathJax for OJS: the bundled MathJax copy is selected for context ' . $contextId . ' but ' . $component . ' is missing under plugins/generic/mathJaxRender/mathjax/es5/ — falling back to the jsDelivr CDN.');
            }
        }
        return 'https://cdn.jsdelivr.net/npm/mathjax@' . self::MATHJAX_VERSION . '/es5/' . $component;
    }

    /**
     * Build the MathJax v3 configuration script from the plugin settings.
     *
     * @param int $contextId
     * @param bool $forBackend Backend config uses its own single-dollar
     *  setting (default ON — article titles are usually written with single
     *  dollars), independent of the frontend delimiter setting.
     *
     * @return string JavaScript source (inline, without <script> tags)
     */
    public function getMathJaxConfig($contextId, $forBackend = false)
    {
        $singleDollar = $forBackend
            ? $this->getSettingWithDefault($contextId, 'backendSingleDollar', true)
            : $this->getSettingWithDefault($contextId, 'singleDollar', false);

        $inlineMath = [];
        if ($singleDollar) {
            $inlineMath[] = ['$', '$'];
        }
        $inlineMath[] = ['\\(', '\\)'];

        $config = [
            'tex' => [
                'inlineMath' => $inlineMath,
                'displayMath' => [['$$', '$$'], ['\\[', '\\]']],
                'processEscapes' => true,
            ],
            'options' => [
                'skipHtmlTags' => ['script', 'noscript', 'style', 'textarea', 'pre', 'code'],
                // The observer marks editing surfaces with this class so that
                // rich-text editors are never typeset (see backend-observer.js).
                'ignoreHtmlClass' => self::IGNORE_CLASS,
            ],
        ];
        if ($forBackend) {
            // The observer owns every backend typeset pass: it marks the
            // editing surfaces first, so MathJax must not run on its own
            // before that has happened.
            $config['startup'] = ['typeset' => false];
        }
        // Titles, abstracts and references are written by authors, so MathJax
        // runs in safe mode: links are limited to http(s) and the class, id
        // and style macros are restricted.
        $load = ['ui/safe'];
        $config['safe'] = ['safeProtocols' => ['http' => true, 'https' => true, 'file' => false, 'javascript' => false, 'data' => false]];
        if ($this->getSettingWithDefault($contextId, 'inputAsciimath', false)) {
            // The AsciiMath input component is not part of any combined build;
            // the MathJax loader resolves it relative to the library URL.
            $load[] = 'input/asciimath';
        }
        $config['loader'] = ['load' => $load];
        return 'window.MathJax = ' . json_encode($config, JSON_UNESCAPED_SLASHES) . ';';
    }
}
