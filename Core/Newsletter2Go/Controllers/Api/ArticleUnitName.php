<?php

class Shopware_Controllers_Api_ArticleUnitName extends Shopware_Controllers_Api_Rest
{
    /**
     * @var Shopware\Components\Api\Resource\ArticleUnitName
     */
    protected $resource = null;

    public function init()
    {
        $this->resource = \Shopware\Components\Api\Manager::getResource('ArticleUnitName');
    }

    /**
     * Get list of media files for specific article
     *
     * GET /api/ArticleUnitName/
     */
    public function indexAction()
    {
        $id = $this->Request()->getParam('unitId');
        $shopId = $this->Request()->getParam('shopId');

        if (isset($id) && isset($shopId)) {
            $data = $this->resource->getArticleUnitName($id, $shopId);
            $result = array(
                'success' => true,
                'message' => 'OK',
                'data' => $data,
            );
        } else {
            $result = array(
                'success' => false,
                'message' => 'Please include unit\'s ID and shop\'s ID in your request',
            );
        }

        $this->View()->assign($result);
    }
}
