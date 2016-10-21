<?php

class Shopware_Controllers_Api_CustomerFields extends Shopware_Controllers_Api_Rest
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
     * GET /api/CustomerFields/
     */
    public function indexAction()
    {
        $fields = $this->resource->getCustomerFields();
        $this->View()->assign(array(
            'success' => true,
            'message' => 'OK',
            'data' => $fields,
        ));
    }
}
