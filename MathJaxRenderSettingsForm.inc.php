<?php

/**
 * @file MathJaxRenderSettingsForm.inc.php
 *
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class MathJaxRenderSettingsForm
 * @ingroup plugins_generic_mathJaxRender
 *
 * @brief Form for journal managers to modify the MathJax for OJS settings.
 */

import('lib.pkp.classes.form.Form');

class MathJaxRenderSettingsForm extends Form {

	/** @var int */
	var $_contextId;

	/** @var MathJaxRenderPlugin */
	var $_plugin;

	/** Boolean settings handled uniformly by this form. */
	var $_boolSettings = array(
		'areaArticle', 'areaGalley', 'areaIssue', 'areaSearch', 'areaHomepage', 'areaBackend',
		'singleDollar', 'backendSingleDollar', 'inputMathml', 'inputAsciimath',
	);

	/**
	 * Constructor
	 * @param $plugin MathJaxRenderPlugin
	 * @param $contextId int
	 */
	function __construct($plugin, $contextId) {
		$this->_contextId = $contextId;
		$this->_plugin = $plugin;

		parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));

		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	/**
	 * Initialize form data.
	 */
	function initData() {
		$plugin = $this->_plugin;
		foreach ($this->_boolSettings as $name) {
			if (isset(MathJaxRenderPlugin::AREA_DEFAULTS[$name])) $default = MathJaxRenderPlugin::AREA_DEFAULTS[$name];
			else $default = ($name == 'backendSingleDollar');
			$this->setData($name, (bool) $plugin->getSettingWithDefault($this->_contextId, $name, $default));
		}
		$this->setData('source', $plugin->getSettingWithDefault($this->_contextId, 'source', 'bundled'));
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->readUserVars(array_merge($this->_boolSettings, array('source')));
	}

	/**
	 * Fetch the form.
	 * @copydoc Form::fetch()
	 */
	function fetch($request, $template = null, $display = false) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign(array(
			'pluginName' => $this->_plugin->getName(),
			'bundledAvailable' => file_exists($this->_plugin->getBundledLibPath()),
			'bundledFallbackActive' => $this->_plugin->isBundledFallbackActive($this->_contextId),
		));
		return parent::fetch($request, $template, $display);
	}

	/**
	 * Save settings.
	 */
	function execute(...$functionArgs) {
		parent::execute(...$functionArgs);

		$plugin = $this->_plugin;
		foreach ($this->_boolSettings as $name) {
			$plugin->updateSetting($this->_contextId, $name, (bool) $this->getData($name), 'bool');
		}
		$source = $this->getData('source');
		if (!in_array($source, array('cdn', 'bundled'))) $source = 'bundled';
		$plugin->updateSetting($this->_contextId, 'source', $source, 'string');

		$request = Application::get()->getRequest();
		$notificationManager = new NotificationManager();
		$notificationManager->createTrivialNotification($request->getUser()->getId(), NOTIFICATION_TYPE_SUCCESS);
	}
}
