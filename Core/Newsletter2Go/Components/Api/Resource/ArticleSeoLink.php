<?php

namespace Shopware\Components\Api\Resource;

use Newsletter2Go\Components\Newsletter2GoHelper;

/**
 * Class ArticleSeoLink
 * @package Shopware\Components\Api\Resource
 */
class ArticleSeoLink extends Resource
{

    /**
     * Retrieves SEO link of an article
     *
     * @param $id
     * @param $shopId
     * @return array
     */
    public function getArticleSeoLink($id, $shopId)
    {
        $this->checkPrivilege('read');
        $router = Shopware()->Container()->get('router');
        $context = $router->getContext();

        $assembleParams = array(
            'module' => 'frontend',
            'sViewport' => 'detail',
            'sArticle' => $id,
        );

        if ($shopId == 0) {
            $helper = new Newsletter2GoHelper();
            $shopId = $helper->getDefaultShopId();
        }

        $context->setShopId($shopId);
        $link = $router->assemble($assembleParams, $context);
        $protocol = $context->isSecure() ? 'https://' : 'http://';
        $baseUrl = $protocol . $context->getHost() . $context->getBaseUrl();

        $result = str_replace($baseUrl, '', $link);

        return ltrim($result, '/');
    }
}
