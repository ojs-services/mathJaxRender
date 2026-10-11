<?php

/**
 * @file MathJaxRenderSettingsForm.php
 *
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class MathJaxRenderSettingsForm
 *
 * @brief Form for journal managers to modify the MathJax for OJS settings.
 */

namespace APP\plugins\generic\mathJaxRender;

use APP\core\Application;
use APP\notification\NotificationManager;
use APP\template\TemplateManager;
use PKP\form\Form;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorPost;

class MathJaxRenderSettingsForm extends Form
{
    /** @var int */
    public $contextId;

    /** @var MathJaxRenderPlugin */
    public $plugin;

    /** Boolean settings handled uniformly by this form. */
    public $boolSettings = [
        'areaArticle', 'areaGalley', 'areaIssue', 'areaSearch', 'areaHomepage', 'areaBackend',
        'singleDollar', 'backendSingleDollar', 'inputMathml', 'inputAsciimath',
    ];

    /**
     * Constructor
     *
     * @param MathJaxRenderPlugin $plugin
     * @param int $contextId
     */
    public function __construct($plugin, $contextId)
    {
        $this->contextId = $contextId;
        $this->plugin = $plugin;

        parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));

        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    /**
     * Initialize form data.
     */
    public function initData()
    {
        $plugin = $this->plugin;
        foreach ($this->boolSettings as $name) {
            if (isset(MathJaxRenderPlugin::AREA_DEFAULTS[$name])) {
                $default = MathJaxRenderPlugin::AREA_DEFAULTS[$name];
            } else {
                $default = ($name == 'backendSingleDollar');
            }
            $this->setData($name, (bool) $plugin->getSettingWithDefault($this->contextId, $name, $default));
        }
        $this->setData('source', $plugin->getSettingWithDefault($this->contextId, 'source', 'bundled'));
    }

    /**
     * Assign form data to user-submitted data.
     */
    public function readInputData()
    {
        $this->readUserVars(array_merge($this->boolSettings, ['source']));
    }

    /**
     * Fetch the form.
     *
     * @copydoc Form::fetch()
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'pluginName' => $this->plugin->getName(),
            'bundledAvailable' => file_exists($this->plugin->getBundledLibPath()),
            'bundledFallbackActive' => $this->plugin->isBundledFallbackActive($this->contextId),
        ]);
        return parent::fetch($request, $template, $display);
    }

    /**
     * Save settings.
     */
    public function execute(...$functionArgs)
    {
        parent::execute(...$functionArgs);

        $plugin = $this->plugin;
        foreach ($this->boolSettings as $name) {
            $plugin->updateSetting($this->contextId, $name, (bool) $this->getData($name), 'bool');
        }
        $source = $this->getData('source');
        if (!in_array($source, ['cdn', 'bundled'])) {
            $source = 'bundled';
        }
        $plugin->updateSetting($this->contextId, 'source', $source, 'string');

        $request = Application::get()->getRequest();
        $notificationManager = new NotificationManager();
        // The default notification type is "success" in every supported release,
        // so the type constant (renamed between 3.4 and 3.5) is not referenced.
        $notificationManager->createTrivialNotification($request->getUser()->getId());
    }
}
