<?php

use Newsletter2Go\Components\Newsletter2GoHelper;
use Newsletter2Go\Subscriber\CookieRegisterer;

/**
 * @category  Shopware
 * @package   Shopware\Plugins\n2goExtendApi
 */
class Shopware_Plugins_Core_Newsletter2Go_Bootstrap extends Shopware_Components_Plugin_Bootstrap
{
    const VERSION = '5.0.0';
    const ERRNO_PLUGIN_OTHER = 'int-1-600';

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
     * Information about the plugin.
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
            'support'     => 'https://www.newsletter2go.com/help/',
            'link'        => 'https://www.newsletter2go.com',
        );
    }

    /**
     * This derived method is executed each time if this plugin will will be installed
     *
     * @return array|bool
     *
     * @throws Exception
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
     * @throws \Doctrine\ORM\ORMInvalidArgumentException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function uninstall()
    {
        $this->removeDatabase();

        $user = Shopware()->Models()
            ->getRepository('Shopware\Models\User\User')
            ->findOneBy(array('username' => 'newsletter2goApiUser'));
        if ($user) {
            Shopware()->Models()->remove($user);
        }

        $menuItem = $this->Menu()->findOneBy(array('label' => 'Newsletter2Go'));
        Shopware()->Models()->remove($menuItem);
        Shopware()->Models()->flush();

        return true;
    }

    /**
     * Add plugin namespaces
     */
    public function onEnlightControllerFrontStartDispatch()
    {
        $this->registerCustomModels();
        $this->Application()->Loader()->registerNamespace('Newsletter2Go\Components', $this->Path() . 'Components/');
        $this->Application()->Loader()->registerNamespace('Shopware\Components', $this->Path() . 'Components/');
        $this->Application()->Loader()->registerNamespace('Newsletter2Go\Services', $this->Path() . 'Services/');
    }

  public function onStartDispatch(Enlight_Event_EventArgs $args)
  {
    $this->Application()->Loader()->registerNamespace('Newsletter2Go\Subscriber', $this->Path() . 'Subscriber/');
    if (Shopware()->Config()->get('show_cookie_note') || Shopware()->Config()->get('swag_cookie.show_cookie_note')) {
      $this->Application()->Events()->addSubscriber(new CookieRegisterer());
    }
  }

    /**
     * Add template path
     */
    public function onGetControllerPathBackendNewsletter2go()
    {
        $this->Application()->Template()->addTemplateDir($this->Path() . 'Views/', '');
    }

    /**
     * Add template path
     *
     * @param Enlight_Event_EventArgs $args
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

        if ($controllerName === 'checkout' && $actionName === 'finish' && $companyId && $tracking && $this->isCookieAccepted($args)) {
            //order confirmation event
            $helper = new Newsletter2GoHelper();
            $view->assign('companyId', $companyId);
            $view->assign('helper', $helper);
            $view->extendsTemplate('frontend/plugins/n2go_jstracking/finish.tpl');
        }
    }

    /**
     * @param \Enlight_Event_EventArgs $args
     * @return bool
     */
    private function isCookieAccepted(\Enlight_Event_EventArgs $args)
    {
      try {
          if ($args->getRequest()->getCookie('allowCookie') == 1) {
            //all cookies accepted
            return true;
          } elseif ($args->getRequest()->getCookie('cookiePreferences')) {
              $cookiePreferences = json_decode($args->getRequest()->getCookie('cookiePreferences'), true);
              if (!empty($cookiePreferences['groups']['statistics']['cookies']['n2g']['active'])) {
                //n2g cookie accepted
                return true;
              }
          }
      } catch (\Exception $exception) {
        Shopware()->Container()->get('pluginlogger')->error($exception->getMessage());
      }

        return false;
    }

    /**
     * Event listener function of the Enlight_Controller_Dispatcher_ControllerPath_Backend_Newsletter2go
     * event. This event is fired when shopware trying to access the plugin Newsletter2go controller.
     *
     * @return string
     */
    public function getNewsletter2goBackendController()
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
     * @return string
     */
    public function getNewsletterCustomersApiController()
    {
        return $this->Path() . 'Controllers/Api/NewsletterCustomers.php';
    }

    /**
     * Event listener function of the Enlight_Controller_Dispatcher_ControllerPath_Api_CustomerFields
     * event. This event is fired when shopware trying to access the plugin CustomerFields controller.
     *
     * @return string
     */
    public function getCustomerFieldsApiController()
    {
        return $this->Path() . 'Controllers/Api/CustomerFields.php';
    }

    /**
     * Event listener function of the Enlight_Controller_Dispatcher_ControllerPath_Api_ArticleMedia
     * event. This event is fired when shopware trying to access the plugin ArticleMedia controller.
     *
     * @return string
     */
    public function getArticleMediaFilesApiController()
    {
        return $this->Path() . 'Controllers/Api/ArticleMediaFiles.php';
    }

    /**
     * Event listener function of the Enlight_Controller_Dispatcher_ControllerPath_Api_CustomerGroups
     * event. This event is fired when shopware trying to access the plugin NewsletterGroups controller.
     *
     * @return string
     */
    public function getNewsletterGroupsApiController()
    {
        return $this->Path() . 'Controllers/Api/NewsletterGroups.php';
    }

    /**
     * Event listener function of the Enlight_Controller_Dispatcher_ControllerPath_Api_NewsletterScriptUrls
     * event. This event is fired when shopware trying to access the plugin NewsletterScriptUrls controller.
     *
     * @return string
     */
    public function getNewsletterScriptUrlsApiController()
    {
        return $this->Path() . 'Controllers/Api/NewsletterScriptUrls.php';
    }

    /**
     * Event listener function of the Enlight_Controller_Dispatcher_ControllerPath_Api_ArticleSeoLink
     * event. This event is fired when shopware trying to access the plugin ArticleSeoLink controller.
     *
     * @return string
     */
    public function getArticleSeoLinkApiController()
    {
        return $this->Path() . 'Controllers/Api/ArticleSeoLink.php';
    }

    protected function registerCustomModels()
    {
        $dir = $this->Path();
        $container = Shopware()->Container();
        $container->get('loader')->registerNamespace('Shopware\Models\Newsletter2Go', $dir . 'Models/');
        $container->get('modelannotations')->addPaths(array($dir . 'Models/'));
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

        if ($node === null) {
            $rootNode = $this->Menu()->findOneBy(array('label' => 'Marketing'));
            $this->createMenuItem(array(
                'label' => 'Newsletter2Go',
                'class' => 'newsletter2go_image',
                'active' => 1,
                'parent' => $rootNode,
                'controller' => 'Newsletter2go',
                'action' => 'index',
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
        $this->subscribeEvent(
          'Enlight_Controller_Front_DispatchLoopStartup',
          'onStartDispatch'
        );
    }

    /**
     * @throws Exception
     */
    private function registerControllers()
    {
        $path = 'Enlight_Controller_Dispatcher_ControllerPath_%s_%s';
        $this->subscribeEvent(sprintf($path, 'Backend', 'Newsletter2go'), 'getNewsletter2goBackendController');
        $this->subscribeEvent(sprintf($path, 'Frontend', 'Newsletter2goCallback'), 'getDefaultControllerPath');
        $this->subscribeEvent(sprintf($path, 'Api', 'NewsletterCustomers'), 'getNewsletterCustomersApiController');
        $this->subscribeEvent(sprintf($path, 'Api', 'NewsletterScriptUrls'), 'getNewsletterScriptUrlsApiController');
        $this->subscribeEvent(sprintf($path, 'Api', 'NewsletterGroups'), 'getNewsletterGroupsApiController');
        $this->subscribeEvent(sprintf($path, 'Api', 'CustomerFields'), 'getCustomerFieldsApiController');
        $this->subscribeEvent(sprintf($path, 'Api', 'ArticleMediaFiles'), 'getArticleMediaFilesApiController');
        $this->subscribeEvent(sprintf($path, 'Api', 'ArticleSeoLink'), 'getArticleSeoLinkApiController');
    }
}
