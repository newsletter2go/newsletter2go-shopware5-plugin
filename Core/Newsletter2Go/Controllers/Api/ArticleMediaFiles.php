<?php

class Shopware_Controllers_Api_ArticleMediaFiles extends Shopware_Controllers_Api_Rest
{
    /**
     * @var Shopware\Components\Api\Resource\ArticleMediaFiles
     */
    protected $resource = null;

    public function init()
    {
        $this->resource = \Shopware\Components\Api\Manager::getResource('ArticleMediaFiles');
    }

    /**
     * Get list of media files for specific article
     *
     * GET /api/ArticleMedia/
     */
    public function indexAction()
    {

        $result = array(
            'success' => false,
            'message' => 'Please send GET request'
        );

        $this->View()->assign($result);
    }

    public function getAction()
    {
        $id = $this->Request()->getParam('id');

        if (isset($id) && !empty($id)){
            $medias = $this->resource->getArticleMediaFiles($id);
            $result = array(
                'success' => true,
                'message' => 'OK',
                'data' => $medias,
            );
        } else {
            $result = array(
                'success' => false,
                'message' => 'Please include article\'s ID in your request',
            );
        }

        $this->View()->assign($result);
    }
}
