<?php

class Shopware_Controllers_Api_CustomerGroups extends Shopware_Controllers_Api_Rest
{
    /**
     * @var Shopware\Components\Api\Resource\NewsletterCustomer
     */
    protected $resource = null;

    public function init()
    {
        $this->resource = \Shopware\Components\Api\Manager::getResource('NewsletterCustomer');
    }

    /**
     * Get list of customer fields
     *
     * GET /api/customerGroups/
     */
    public function indexAction()
    {
        $groups = $this->resource->getCustomerGroups();
        $this->View()->assign(array(
            'success' => true,
            'message' => 'OK',
            'data'    => $groups,
        ));
    }

    /**
     * Plugin Version
     *
     * GET /api/customerGroups/pluginVersion/
     */
    public function getAction()
    {
        $version = $this->resource->getPluginVersion();
        $this->View()->assign(array(
            'success' => true,
            'message' => 'OK',
            'data'    => $version,
        ));
    }
}
