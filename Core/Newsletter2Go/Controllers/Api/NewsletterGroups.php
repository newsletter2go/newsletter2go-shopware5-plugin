<?php

class Shopware_Controllers_Api_NewsletterGroups extends Shopware_Controllers_Api_Rest
{
    /**
     * @var Shopware\Components\Api\Resource\NewsletterCustomer
     */
    protected $resource;

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
        try {
            $groups = $this->resource->getNewsletterGroups();
            $this->View()->assign([
                'success' => true,
                'message' => 'OK',
                'data' => $groups,
            ]);
        } catch (\Exception $e) {
            $this->View()->assign([
                'success' => false,
                'message' => $e->getMessage(),
                'errorcode' => \Shopware_Plugins_Core_Newsletter2Go_Bootstrap::ERRNO_PLUGIN_OTHER,
            ]);
        }
    }

    /**
     * Plugin Version
     *
     * GET /api/customerGroups/pluginVersion/
     */
    public function getAction()
    {
        try {
            $this->View()->assign([
                'success' => true,
                'message' => 'OK',
                'data' => $this->resource->getPluginVersion(),
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
