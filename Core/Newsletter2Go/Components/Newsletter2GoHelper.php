<?php

namespace Newsletter2Go\Components;

class Newsletter2GoHelper
{
    /**
     * Returns the id of the default shop.
     *
     * @return string
     */
    public function getDefaultShopId()
    {
        return Shopware()->Db()->fetchOne(
            'SELECT id FROM s_core_shops WHERE `default` = 1'
        );
    }
}
