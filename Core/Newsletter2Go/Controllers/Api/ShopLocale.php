<?php

class Shopware_Controllers_Api_ShopLocale extends Shopware_Controllers_Api_Rest
{
    /**
     * @var Shopware\Components\Api\Resource\ShopLocale
     */
    protected $resource;

    public function init()
    {
        $this->resource = \Shopware\Components\Api\Manager::getResource('ShopLocale');
    }

    /**
     * GET /api/shopLocale/
     */
    public function indexAction()
    {
        try {
            $this->View()->assign([
                'locale' => $this->resource->getShopLocale($this->Request()->getParam('shopId')),
                'success' => true,
            ]);
        } catch (\Exception $e) {
            $this->View()->assign([
                'success' => false,
                'message' => $e->getMessage(),
                'errorcode' => \Shopware_Plugins_Core_Newsletter2Go_Bootstrap::ERRNO_PLUGIN_OTHER,
            ]);
        }
    }
}
