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
    public function getAction()
    {
        $this->View()->assign([
            'locale' => $this->resource->getShopLocale($this->Request()->getParam('id')),
            'success' => true,
        ]);
    }
}
