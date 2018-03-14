<?php

use Newsletter2Go\Components\Newsletter2GoHelper;

/**
 * @category  Shopware
 * @package   Shopware\Plugins\n2goExtendApi
 */
class Shopware_Plugins_Core_Newsletter2Go_Bootstrap extends Shopware_Components_Plugin_Bootstrap
{
    const VERSION = '4.1.17';

    /**
     * Capabilities for plugin.
     *
     * @return array
     */
    public function getCapabilities()
    {
        return array(
            'install' => true,
            'update'  => true,
            'enable'  => true,
        );
    }

    /**
     * Label for plugin.
     *
     * @return string
     */
    public function getLabel()
    {
        return 'Newsletter2Go E-Mail Marketing';
    }

    /**
     * Version for plugin.
     *
     * @return string
     */
    public function getVersion()
    {
        return static::VERSION;
    }

    /**
     * Informations about plugin.
     *
     * @return array
     */
    public function getInfo()
    {
        return array(
            'version'     => $this->getVersion(),
            'label'       => $this->getLabel(),
            'author'      => 'Newsletter2Go',
            'copyright'   => 'Copyright © ' . date('Y') . ', Newsletter2Go GmbH',
            'supplier'    => 'Newsletter2Go GmbH',
            'description' => 'Adds email marketing functionality to your E-commerce platform. Easily synchronize your contacts and send product newsletters',
            'support'     => 'https://www.newsletter2go.de/hilfe/',
            'link'        => 'http://www.newsletter2go.de',
        );
    }

    /**
     * This derived method is executed each time if this plugin will will be installed
     *
     * @return array
     */
    public function install()
    {
        $this->createDatabase();
        $this->createMenu();
        $this->registerControllers();
        $this->registerEvents();

        return array(
            'success'         => true,
            'invalidateCache' => array('frontend', 'backend'),
        );
    }

    /**
     * Remove attributes from table
     *
     * @return bool
     */
    public function uninstall()
    {
        $this->removeDatabase();

        $user = Shopware()->Models()->getRepository('Shopware\Models\User\User')
            ->findOneBy(array('username' => 'newsletter2goApiUser'));
        if ($user) {
            Shopware()->Models()->remove($user);
        }

        /* @var $rootNode  \Shopware\Models\Menu\Menu */
        $menuItem = $this->Menu()->findOneBy(array('label' => 'Newsletter2Go'));
        Shopware()->Models()->remove($menuItem);
        Shopware()->Models()->flush();

        return true;
    }

    /**
     * @param Enlight_Event_EventArgs $args
     */
    public function onEnlightControllerFrontStartDispatch(Enlight_Event_EventArgs $args)
    {
        $this->registerCustomModels();
        $this->Application()->Loader()->registerNamespace('Newsletter2Go\Components', $this->Path() . 'Components/');
        $this->Application()->Loader()->registerNamespace('Shopware\Components', $this->Path() . 'Components/');
    }

    /**
     * Add template path
     *
     * @param Enlight_Event_EventArgs $args
     *
     * @return string
     */
    public function onGetControllerPathBackendNewsletter2go(Enlight_Event_EventArgs $args)
    {
        $this->Application()->Template()->addTemplateDir($this->Path() . 'Views/', '');
    }

    /**
     * Add template path
     *
     * @param Enlight_Event_EventArgs $args
     *
     * @return string
     */
    public function onBackendPostDispatch(Enlight_Event_EventArgs $args)
    {
        /** @var $args Enlight_Controller_ActionEventArgs */
        /** @var $view Enlight_View_Default */
        $view = $args->getSubject()->View();
        // Add template directory
        $view->addTemplateDir($this->Path() . 'Views/');
        if ($args->getRequest()->getActionName() === 'index') {
            $view->extendsTemplate('backend/plugin/base/header.tpl');
        }
    }

    /**
     * Called when the FrontendPostDispatch Event is triggered
     *
     * @param Enlight_Event_EventArgs $args
     */
    public function onFrontendPostDispatch(Enlight_Event_EventArgs $args)
    {
        /** @var $args Enlight_Controller_ActionEventArgs */
        /** @var Enlight_View_Default $view */
        $view = $args->getSubject()->View();

        /* @var Enlight_Controller_Request_RequestHttp $request */
        $request = $args->getRequest();

        $view->addTemplateDir($this->Path() . 'Views/');

        $repository = Shopware()->Models()->getRepository('Shopware\Models\Newsletter2Go\Newsletter2Go');
        $companyModel = $repository->findOneBy(array('name' => 'companyId'));
        $trackOrdersModel =  $repository->findOneBy(array('name' => 'trackOrders'));
        if (!$companyModel || !$trackOrdersModel) {
            return;
        }

        $companyId = $companyModel->getValue();
        $tracking = $trackOrdersModel->getValue();

        $actionName = $request->getActionName();
        $controllerName = $request->getControllerName();

        if ($controllerName === 'checkout' && $actionName === 'finish' && $companyId && $tracking) {
            //order confirmation event
            $helper = new Newsletter2GoHelper();
            $view->assign('companyId', $companyId);
            $view->assign('helper', $helper);
            $view->extendsTemplate('frontend/plugins/n2go_jstracking/finish.tpl');
        }
    }

    /**
     * Event listener function of the Enlight_Controller_Dispatcher_ControllerPath_Backend_Newsletter2go
     * event. This event is fired when shopware trying to access the plugin Newsletter2go controller.
     *
     * @param Enlight_Event_EventArgs $arguments
     * @return string
     */
    public function getNewsletter2goBackendController(Enlight_Event_EventArgs $arguments)
    {
        $this->Application()->Template()->addTemplateDir(
            $this->Path() . 'Views/', 'newsletter2go'
        );

        return $this->Path() . 'Controllers/Backend/Newsletter2go.php';
    }

    /**
     * Event listener function of the Enlight_Controller_Dispatcher_ControllerPath_Api_NewsletterCustomers
     * event. This event is fired when shopware trying to access the plugin NewsletterCustomers controller.
     *
     * @param Enlight_Event_EventArgs $arguments
     * @return string
     */
    public function getNewsletterCustomersApiController(Enlight_Event_EventArgs $arguments)
    {
        return $this->Path() . 'Controllers/Api/NewsletterCustomers.php';
    }

    /**
     * Event listener function of the Enlight_Controller_Dispatcher_ControllerPath_Api_CustomerFields
     * event. This event is fired when shopware trying to access the plugin CustomerFields controller.
     *
     * @param Enlight_Event_EventArgs $arguments
     * @return string
     */
    public function getCustomerFieldsApiController(Enlight_Event_EventArgs $arguments)
    {
        return $this->Path() . 'Controllers/Api/CustomerFields.php';
    }

    /**
     * Event listener function of the Enlight_Controller_Dispatcher_ControllerPath_Api_ArticleMedia
     * event. This event is fired when shopware trying to access the plugin ArticleMedia controller.
     *
     * @param Enlight_Event_EventArgs $arguments
     * @return string
     */
    public function getArticleMediaFilesApiController(Enlight_Event_EventArgs $arguments)
    {
        return $this->Path() . 'Controllers/Api/ArticleMediaFiles.php';
    }

    /**
     * Event listener function of the Enlight_Controller_Dispatcher_ControllerPath_Api_CustomerGroups
     * event. This event is fired when shopware trying to access the plugin NewsletterGroups controller.
     *
     * @param Enlight_Event_EventArgs $arguments
     * @return string
     */
    public function getNewsletterGroupsApiController(Enlight_Event_EventArgs $arguments)
    {
        return $this->Path() . 'Controllers/Api/NewsletterGroups.php';
    }

    /**
     * Event listener function of the Enlight_Controller_Dispatcher_ControllerPath_Api_NewsletterScriptUrls
     * event. This event is fired when shopware trying to access the plugin NewsletterScriptUrls controller.
     *
     * @param Enlight_Event_EventArgs $arguments
     * @return string
     */
    public function getNewsletterScriptUrlsApiController(Enlight_Event_EventArgs $arguments)
    {
        return $this->Path() . 'Controllers/Api/NewsletterScriptUrls.php';
    }
    
    /**
     * Event listener function of the Enlight_Controller_Dispatcher_ControllerPath_Api_ArticleSeoLink
     * event. This event is fired when shopware trying to access the plugin ArticleSeoLink controller.
     *
     * @param Enlight_Event_EventArgs $arguments
     * @return string
     */
    public function getArticleSeoLinkApiController(Enlight_Event_EventArgs $arguments)
    {
        return $this->Path() . 'Controllers/Api/ArticleSeoLink.php';
    }

    protected function registerCustomModels()
    {
        $this->Application()->Loader()->registerNamespace(
            'Shopware\Models\Newsletter2Go', $this->Path() . 'Models/'
        );
        $this->Application()->ModelAnnotations()->addPaths(
            array(
                $this->Path() . 'Models/',
            )
        );
    }

    private function executeSchemaAction($action)
    {
        $em = $this->Application()->Models();
        $tool = new \Doctrine\ORM\Tools\SchemaTool($em);

        $classes = array(
            $em->getClassMetadata('Shopware\Models\Newsletter2Go\Newsletter2Go'),
        );

        try {
            $tool->$action($classes);
        } catch (\Doctrine\ORM\Tools\ToolsException $e) {
            //ignore
        }
    }

    /**
     * Create a back-end menu item
     */
    private function createMenu()
    {
        $node = $this->Menu()->findOneBy(array('label' => 'Newsletter2Go'));

        if(!isset($node)) {
            $rootNode = $this->Menu()->findOneBy(array('label' => 'Marketing'));
            $this->createMenuItem(array(
                'label'      => 'Newsletter2Go',
                'class'      => 'newsletter2go_image',
                'active'     => 1,
                'parent'     => $rootNode,
                'controller' => 'Newsletter2go',
                'action'     => 'index',
            ));
        }
    }

    /**
     * Creates database table based on Newsletter2Go model
     */
    private function createDatabase()
    {
        // Register namespace and annotations for custom model
        $this->registerCustomModels();
        // Create schema for custom model
        $this->executeSchemaAction('createSchema');
    }

    /**
     * Drops database table based on Newsletter2Go model
     */
    private function removeDatabase()
    {
        // Unregister namespace and annotations for custom model
        $this->registerCustomModels();
        // Remove schema for custom model
        $this->executeSchemaAction('dropSchema');
    }

    /**
     * Registers all necessary events and hooks.
     */
    private function registerEvents()
    {
        $this->subscribeEvent('Enlight_Controller_Action_PostDispatch_Backend_Index', 'onBackendPostDispatch');
        $this->subscribeEvent('Enlight_Controller_Front_StartDispatch', 'onEnlightControllerFrontStartDispatch');
        $this->subscribeEvent('Enlight_Controller_Dispatcher_ControllerPath_Backend_Newsletter2go', 'onGetControllerPathBackendNewsletter2go');
        $this->subscribeEvent('Enlight_Controller_Action_PostDispatch_Frontend', 'onFrontendPostDispatch');
    }

    private function registerControllers()
    {
        // Added to support older versions (<4.2.0)
        if (method_exists($this, 'registerController')) {
            $this->registerController('Frontend', 'Newsletter2goCallback');
            $this->registerController('Backend', 'Newsletter2go');
            $this->registerController('Api', 'NewsletterCustomers');
            $this->registerController('Api', 'NewsletterScriptUrls');
            $this->registerController('Api', 'NewsletterGroups');
            $this->registerController('Api', 'CustomerFields');
            $this->registerController('Api', 'ArticleMediaFiles');
            $this->registerController('Api', 'ArticleSeoLink');
        } else {
            $path = 'Enlight_Controller_Dispatcher_ControllerPath_';
            $this->subscribeEvent($path . 'Backend_Newsletter2go', 'getNewsletter2goBackendController');
            $this->subscribeEvent($path . 'Api_NewsletterCustomers', 'getNewsletterCustomersApiController');
            $this->subscribeEvent($path . 'Api_NewsletterScriptUrls', 'getNewsletterScriptUrlsApiController');
            $this->subscribeEvent($path . 'Api_NewsletterGroups', 'getNewsletterGroupsApiController');
            $this->subscribeEvent($path . 'Api_CustomerFields', 'getCustomerFieldsApiController');
            $this->subscribeEvent($path . 'Api_ArticleMediaFiles', 'getArticleMediaFilesApiController');
            $this->subscribeEvent($path . 'Api_ArticleSeoLink', 'getArticleSeoLinkApiController');
        }
    }

}
