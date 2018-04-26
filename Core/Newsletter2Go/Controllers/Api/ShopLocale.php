<?php

class Shopware_Controllers_Api_ShopLocale extends Shopware_Controllers_Api_Rest
{
    /**
     * GET /api/shopLocale/
     */
    public function getAction()
    {
        $this->View()->assign([
            'locale' => Shopware()->Db()
                ->fetchOne(
                    'SELECT locale_id FROM s_core_shops WHERE `id` = ?',
                    [$this->Request()->getParam('id')]
                ),
            'success' => true,
        ]);
    }
}
