<?php

namespace Shopware\Components\Api\Resource;

/**
 * Class ShopLocale
 * @package Shopware\Components\Api\Resource
 */
class ShopLocale extends Resource
{
    /**
     * Retrieves the locale id for a shop
     *
     * @param int $shopId
     *
     * @return int
     *
     * @throws \Shopware\Components\Api\Exception\PrivilegeException
     */
    public function getShopLocale($shopId)
    {
        $this->checkPrivilege('read');

        return (int)Shopware()->Db()
            ->fetchOne(
                'SELECT locale_id FROM s_core_shops WHERE `id` = ?',
                [$shopId]
            );
    }
}
