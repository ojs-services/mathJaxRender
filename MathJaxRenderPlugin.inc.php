<?php

/**
 * @file MathJaxRenderPlugin.inc.php
 *
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class MathJaxRenderPlugin
 * @ingroup plugins_generic_mathJaxRender
 *
 * @brief MathJax for OJS — renders LaTeX/TeX mathematics on journal pages by
 *        injecting a MathJax configuration and the MathJax library script.
 */

import('lib.pkp.classes.plugins.GenericPlugin');

class MathJaxRenderPlugin extends GenericPlugin {

	/** Pinned MathJax version. Never track "latest". */
	const MATHJAX_VERSION = '3.2.2';

	/** Class marking subtrees MathJax must leave alone (editing surfaces). */
	const IGNORE_CLASS = 'mathJaxRender_ignore';

	/** Site areas MathJax can be enabled for, with their default state. */
	const AREA_DEFAULTS = array(
		'areaArticle' => true,
		'areaGalley' => true,
		'areaIssue' => true,
		'areaSearch' => true,
		'areaHomepage' => false,
		'areaBackend' => false,
	);

	/** @var boolean Whether an HTML galley output capture is in progress */
	var $_galleyBufferActive = false;

	/**
	 * @copydoc Plugin::register()
	 */
	function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path, $mainContextId);
		if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) return $success;
		if ($success && $this->getEnabled($mainContextId)) {
			HookRegistry::register('TemplateManager::display', array($this, 'injectMathJax'));
			// The HTML galley wrapper page has its own minimal template that
			// never calls {load_script}, so registered scripts cannot reach it;
			// its footer hook is the only insertion point.
			HookRegistry::register('Templates::Common::Footer::PageFooter', array($this, 'injectGalleyWrapperFooter'));
			// HTML galley documents are echoed directly (not templated); these
			// two hooks bracket that echo, allowing an output-buffer capture.
			HookRegistry::register('HtmlArticleGalleyPlugin::articleDownload', array($this, 'startGalleyOutputBuffer'));
			HookRegistry::register('HtmlArticleGalleyPlugin::articleDownloadFinished', array($this, 'finishGalleyOutputBuffer'));
		}
		return $success;
	}

	/**
	 * @copydoc Plugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.generic.mathJaxRender.displayName');
	}

	/**
	 * @copydoc Plugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.generic.mathJaxRender.description');
	}

	/**
	 * @copydoc Plugin::getActions()
	 */
	function getActions($request, $verb) {
		$router = $request->getRouter();
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		return array_merge(
			$this->getEnabled() ? array(
				new LinkAction(
					'settings',
					new AjaxModal(
						$router->url($request, null, null, 'manage', null, array('verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic')),
						$this->getDisplayName()
					),
					__('manager.plugins.settings'),
					null
				),
			) : array(),
			parent::getActions($request, $verb)
		);
	}

	/**
	 * @copydoc Plugin::manage()
	 */
	function manage($args, $request) {
		switch ($request->getUserVar('verb')) {
			case 'settings':
				$context = $request->getContext();

				$this->import('MathJaxRenderSettingsForm');
				$form = new MathJaxRenderSettingsForm($this, $context ? $context->getId() : CONTEXT_ID_NONE);

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
	 * @param $contextId int
	 * @param $name string
	 * @param $default mixed
	 * @return mixed
	 */
	function getSettingWithDefault($contextId, $name, $default) {
		$value = $this->getSetting($contextId, $name);
		return $value === null ? $default : $value;
	}

	/**
	 * Determine which configurable site area the current request belongs to.
	 * @param $request PKPRequest
	 * @return string|null One of the AREA_DEFAULTS keys, or null when the
	 *  page is not covered by any configurable frontend area.
	 */
	function getRequestedArea($request) {
		$router = $request->getRouter();
		$page = $request->getRequestedPage();
		$op = $request->getRequestedOp();

		switch ($page) {
			case 'article':
				if ($op != 'view') return null;
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
	 * @param $hookName string `TemplateManager::display`
	 * @param $args array [&$templateMgr, &$template]
	 * @return boolean Hook processing continues
	 */
	function injectMathJax($hookName, $args) {
		$templateMgr = $args[0];
		$request = Application::get()->getRequest();

		// Never inject into non-page operations (e.g. component/API requests).
		$router = $request->getRouter();
		if (!is_a($router, 'PageRouter')) return false;

		// Settings are stored per journal; skip site-level pages.
		$context = $request->getContext();
		if (!$context) return false;
		$contextId = $context->getId();

		// Backend (editorial UI) rendering — experimental. Scripts registered
		// with the 'backend' context are only ever printed by backend layouts,
		// so no page detection is needed here.
		if ($this->getSettingWithDefault($contextId, 'areaBackend', self::AREA_DEFAULTS['areaBackend'])) {
			$templateMgr->addJavaScript(
				'mathJaxRenderBackendConfig',
				$this->getMathJaxConfig($contextId, true),
				array(
					'inline' => true,
					'contexts' => 'backend',
					'priority' => STYLE_SEQUENCE_CORE,
				)
			);
			$templateMgr->addJavaScript(
				'mathJaxRenderLib',
				$this->getMathJaxUrl($request, $contextId),
				array(
					'contexts' => 'backend',
					'priority' => STYLE_SEQUENCE_NORMAL,
				)
			);
			$templateMgr->addJavaScript(
				'mathJaxRenderObserver',
				// Versioned by the plugin, not by OJS: an upgraded plugin must
				// never be served a browser-cached copy of the old observer.
				$request->getBaseUrl() . '/' . $this->getPluginPath() . '/js/backend-observer.js?v=' . $this->getPluginVersionString(),
				array(
					'contexts' => 'backend',
					'priority' => STYLE_SEQUENCE_LATE,
				)
			);
		}

		// Frontend areas.
		$area = $this->getRequestedArea($request);
		if ($area === null) return false;
		if (!$this->getSettingWithDefault($contextId, $area, self::AREA_DEFAULTS[$area])) return false;

		// The HTML galley wrapper page is served by its own template, which is
		// where injectGalleyWrapperFooter() puts the scripts. Registering them
		// here as well would double-load MathJax under themes that override
		// that template and print registered scripts themselves.
		if ($area === 'areaGalley') return false;

		$templateMgr->addJavaScript(
			'mathJaxRenderConfig',
			$this->getMathJaxConfig($contextId),
			array(
				'inline' => true,
				'contexts' => 'frontend',
				'priority' => STYLE_SEQUENCE_CORE,
			)
		);
		$templateMgr->addJavaScript(
			'mathJaxRenderLib',
			$this->getMathJaxUrl($request, $contextId),
			array(
				'contexts' => 'frontend',
				'priority' => STYLE_SEQUENCE_NORMAL,
			)
		);
		// Typeset content that the page inserts after it has loaded, such as
		// the citation formats of the "How to cite" box.
		$templateMgr->addJavaScript(
			'mathJaxRenderFrontendObserver',
			$request->getBaseUrl() . '/' . $this->getPluginPath() . '/js/frontend-observer.js?v=' . $this->getPluginVersionString(),
			array(
				'contexts' => 'frontend',
				'priority' => STYLE_SEQUENCE_LATE,
			)
		);
		// Core stylesheets hide aria-hidden elements inside the citation box,
		// which would also hide the formulas MathJax draws there.
		$templateMgr->addStyleSheet(
			'mathJaxRenderFrontend',
			'mjx-container[jax="CHTML"] > mjx-math[aria-hidden="true"] { display: inline-block !important; }',
			array(
				'inline' => true,
				'contexts' => 'frontend',
			)
		);
		return false;
	}

	/**
	 * Print the MathJax scripts in the footer of the HTML galley wrapper page,
	 * whose template does not call {load_script}. The wrapper shows the article
	 * title, which may itself contain mathematics. Regular pages are skipped
	 * here — they receive the scripts through addJavaScript().
	 *
	 * @param $hookName string `Templates::Common::Footer::PageFooter`
	 * @param $args array [&$params, $smarty, &$output]
	 * @return boolean Hook processing continues
	 */
	function injectGalleyWrapperFooter($hookName, $args) {
		$request = Application::get()->getRequest();
		$router = $request->getRouter();
		if (!is_a($router, 'PageRouter')) return false;

		$context = $request->getContext();
		if (!$context) return false;
		$contextId = $context->getId();

		if ($this->getRequestedArea($request) !== 'areaGalley') return false;
		if (!$this->getSettingWithDefault($contextId, 'areaGalley', self::AREA_DEFAULTS['areaGalley'])) return false;

		$args[2] .= '<script>' . $this->getMathJaxConfig($contextId) . '</script>'
			. '<script src="' . htmlspecialchars($this->getMathJaxUrl($request, $contextId), ENT_QUOTES) . '"></script>';
		return false;
	}

	/**
	 * Start capturing the HTML galley document that
	 * HtmlArticleGalleyPlugin::articleDownloadCallback() is about to echo.
	 * @param $hookName string `HtmlArticleGalleyPlugin::articleDownload`
	 * @param $args array [&$article, &$galley, &$fileId]
	 * @return boolean false so the galley plugin proceeds with its output
	 */
	function startGalleyOutputBuffer($hookName, $args) {
		$request = Application::get()->getRequest();
		$context = $request->getContext();
		if (!$context) return false;
		if (!$this->getSettingWithDefault($context->getId(), 'areaGalley', self::AREA_DEFAULTS['areaGalley'])) return false;

		$this->_galleyBufferActive = true;
		ob_start();
		return false;
	}

	/**
	 * Inject the MathJax scripts into the captured HTML galley document,
	 * before </body> when present (appended otherwise), and release it.
	 * @param $hookName string `HtmlArticleGalleyPlugin::articleDownloadFinished`
	 * @param $args array [&$returner]
	 * @return boolean Hook processing continues
	 */
	function finishGalleyOutputBuffer($hookName, $args) {
		if (!$this->_galleyBufferActive) return false;
		$this->_galleyBufferActive = false;

		$html = ob_get_clean();
		$request = Application::get()->getRequest();
		$contextId = $request->getContext()->getId();

		$scripts = '<script>' . $this->getMathJaxConfig($contextId) . '</script>'
			. '<script src="' . htmlspecialchars($this->getMathJaxUrl($request, $contextId), ENT_QUOTES) . '"></script>';

		// Insert before </body> without regex replacement: backslash sequences
		// in the config (TeX delimiters) must survive verbatim.
		$pos = stripos($html, '</body>');
		if ($pos !== false) $html = substr_replace($html, $scripts, $pos, 0);
		else $html .= $scripts;

		echo $html;
		return false;
	}

	/**
	 * Pick the MathJax combined component for the current input settings.
	 * @param $contextId int
	 * @return string Filename within the es5 directory
	 */
	function getMathJaxComponent($contextId) {
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
	 * @return string
	 */
	function getPluginVersionString() {
		$version = $this->getCurrentVersion();
		return $version ? $version->getVersionString() : self::MATHJAX_VERSION;
	}

	/**
	 * Absolute filesystem path of the bundled MathJax base component, used
	 * to detect whether the self-hosted copy ships with this installation.
	 * @return string
	 */
	function getBundledLibPath() {
		return dirname(__FILE__) . '/mathjax/es5/tex-chtml.js';
	}

	/**
	 * Whether the bundled (self-hosted) copy is configured but its files are
	 * missing on disk, i.e. the plugin is falling back to the CDN.
	 * @param $contextId int
	 * @return boolean
	 */
	function isBundledFallbackActive($contextId) {
		return $this->getSettingWithDefault($contextId, 'source', 'bundled') == 'bundled'
			&& !file_exists($this->getBundledLibPath());
	}

	/**
	 * Build the URL of the MathJax library for the configured source.
	 * When the bundled copy is selected but its files are missing on disk,
	 * fall back to the CDN so formulas never break — and make that fallback
	 * visible (settings-form warning + error log) since an administrator who
	 * chose self-hosting for privacy reasons must be able to notice it.
	 * @param $request PKPRequest
	 * @param $contextId int
	 * @return string
	 */
	function getMathJaxUrl($request, $contextId) {
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
	 * @param $contextId int
	 * @param $forBackend boolean Backend config uses its own single-dollar
	 *  setting (default ON — article titles are usually written with single
	 *  dollars), independent of the frontend delimiter setting.
	 * @return string JavaScript source (inline, without <script> tags)
	 */
	function getMathJaxConfig($contextId, $forBackend = false) {
		$singleDollar = $forBackend
			? $this->getSettingWithDefault($contextId, 'backendSingleDollar', true)
			: $this->getSettingWithDefault($contextId, 'singleDollar', false);

		$inlineMath = array();
		if ($singleDollar) {
			$inlineMath[] = array('$', '$');
		}
		$inlineMath[] = array('\\(', '\\)');

		$config = array(
			'tex' => array(
				'inlineMath' => $inlineMath,
				'displayMath' => array(array('$$', '$$'), array('\\[', '\\]')),
				'processEscapes' => true,
			),
			'options' => array(
				'skipHtmlTags' => array('script', 'noscript', 'style', 'textarea', 'pre', 'code'),
				// The observer marks editing surfaces with this class so that
				// rich-text editors are never typeset (see backend-observer.js).
				'ignoreHtmlClass' => self::IGNORE_CLASS,
			),
		);
		if ($forBackend) {
			// The observer owns every backend typeset pass: it marks the
			// editing surfaces first, so MathJax must not run on its own
			// before that has happened.
			$config['startup'] = array('typeset' => false);
		}
		// Titles, abstracts and references are written by authors, so MathJax
		// runs in safe mode: links are limited to http(s) and the class, id
		// and style macros are restricted.
		$load = array('ui/safe');
		$config['safe'] = array('safeProtocols' => array('http' => true, 'https' => true, 'file' => false, 'javascript' => false, 'data' => false));
		if ($this->getSettingWithDefault($contextId, 'inputAsciimath', false)) {
			// The AsciiMath input component is not part of any combined build;
			// the MathJax loader resolves it relative to the library URL.
			$load[] = 'input/asciimath';
		}
		$config['loader'] = array('load' => $load);
		return 'window.MathJax = ' . json_encode($config, JSON_UNESCAPED_SLASHES) . ';';
	}
}
