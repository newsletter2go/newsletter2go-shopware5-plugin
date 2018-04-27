<?php

namespace Shopware\Components\Api\Resource;

class ShopLocale extends Resource
{
    /**
     * @param int $shopId
     *
     * @return int
     */
    public function getShopLocale($shopId)
    {
        return (int)Shopware()->Db()
            ->fetchOne(
                'SELECT locale_id FROM s_core_shops WHERE `id` = ?',
                [$shopId]
            );
    }
}
