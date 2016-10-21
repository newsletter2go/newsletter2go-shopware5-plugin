<?php

class Shopware_Controllers_Api_NewsletterScriptUrls extends Shopware_Controllers_Api_Rest
{
    /**
     * @var Shopware\Components\Api\Resource\NewsletterScriptUrl
     */
    protected $resource = null;

    public function init()
    {
        $this->resource = \Shopware\Components\Api\Manager::getResource('NewsletterScriptUrl');
    }

    /**
     * Inserts script url
     *
     * POST /api/newsletterScriptUrls/
     */
    public function postAction()
    {
        $url = $this->Request()->getPost();
        $result = $this->resource->insertUrl($url);
        $this->View()->assign(array('success' => $result));
    }
}
