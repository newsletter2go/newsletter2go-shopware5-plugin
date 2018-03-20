<?php

class Shopware_Controllers_Api_ArticleSeoLink extends Shopware_Controllers_Api_Rest
{
    /**
     * @var Shopware\Components\Api\Resource\ArticleSeoLink
     */
    protected $resource;

    public function init()
    {
        $this->resource = \Shopware\Components\Api\Manager::getResource('ArticleSeoLink');
    }

    /**
     * Get list of media files for specific article
     *
     * GET /api/ArticleSeoLink/
     *
     * @throws \Shopware\Components\Api\Exception\PrivilegeException
     */
    public function indexAction()
    {
        $id = $this->Request()->getParam('identifier');
        $shopId = $this->Request()->getParam('shopId');

        if (isset($id, $shopId)) {
            $data = $this->resource->getArticleSeoLink($id, $shopId);
            $result = array(
                'success' => true,
                'message' => 'OK',
                'data' => $data,
            );
        } else {
            $result = array(
                'success' => false,
                'message' => 'Please include article\'s ID and shop ID in your request',
            );
        }

        $this->View()->assign($result);
    }
}
