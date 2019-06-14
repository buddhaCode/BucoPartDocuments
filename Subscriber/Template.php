<?php

namespace BucoPartDocuments\Subscriber;

use Enlight\Event\SubscriberInterface;
use Shopware\Components\Plugin\CachedConfigReader;

class Template implements SubscriberInterface
{
    /** @var string */
    private $pluginDir;

    /** @var string */
    private $pluginName;

    /** @var CachedConfigReader */
    private $configReader;

    public function __construct(string $pluginDir, string $pluginName, CachedConfigReader $configReader)
    {
        $this->pluginDir = $pluginDir;
        $this->pluginName = $pluginName;
        $this->configReader = $configReader;
    }

    public static function getSubscribedEvents()
    {
        return [
            'Theme_Inheritance_Template_Directories_Collected' => 'addTemplateDir',
            'Enlight_Controller_Action_PostDispatch_Backend_Base' => 'extendExtJsBase',
            'Enlight_Controller_Action_PostDispatchSecure_Backend_Order' => 'extendExtJsOrder',
        ];
    }

    public function addTemplateDir(\Enlight_Event_EventArgs $args)
    {
        $dirs = $args->getReturn();
        $dirs[] = $this->pluginDir . '/Resources/views/';

        $args->setReturn($dirs);
    }

    public function extendExtJsOrder(\Enlight_Event_EventArgs $args)
    {
        $view = $args->getSubject()->View();

        if ($args->getRequest()->getActionName() === 'index') {
            $view->addTemplateDir($this->pluginDir . '/Resources/views/'); // Theme event above doesn't cover backend templates

//            $view->extendsTemplate('backend/order/Buco.grid.column.mixin.PartDocument.js');
            $view->extendsTemplate('backend/order/Buco.grid.column.Icon.js');
        }

        if ($args->getRequest()->getActionName() === 'load') {
            $view->addTemplateDir($this->pluginDir . '/Resources/views/'); // Theme event above doesn't cover backend templates

            $view->extendsTemplate('backend/order/view/detail/configuration-BucoPartDocuments.js');
            $view->extendsTemplate('backend/order/view/list/document-BucoPartDocuments.js');
            $view->extendsTemplate('backend/order/view/list/filter-BucoPartDocuments.js');
            $view->extendsTemplate('backend/order/view/mail/attachment-BucoPartDocuments.js');
            $view->extendsTemplate('backend/order/model/receipt-BucoPartDocuments.js');
            $view->extendsTemplate('backend/order/model/configuration-BucoPartDocuments.js');
            $view->extendsTemplate('backend/order/controller/detail-BucoPartDocuments.js');
            $view->extendsTemplate('backend/order/controller/mail-BucoPartDocuments.js');

            $view->assign('bucoProhibitDocTypeIds', $this->getProhibitDocTypeIdsConfig());
        }
    }

    public function extendExtJsBase(\Enlight_Event_EventArgs $args)
    {
        $view = $args->getSubject()->View();

        $view->addTemplateDir($this->pluginDir . '/Resources/views/'); // Theme event above doesn't cover backend templates
        $view->extendsTemplate('backend/base/attribute/Buco.attribute.Form-BucoPartDocuments.js');
    }

    /**
     * Convert default int value to array value.
     *
     * @return array
     */
    private function getProhibitDocTypeIdsConfig()
    {
        $val = $this->configReader->getByPluginName($this->pluginName)['prohibitDocTypeIds'];

        return is_array($val) ? $val : [(int)$val];
    }
}
