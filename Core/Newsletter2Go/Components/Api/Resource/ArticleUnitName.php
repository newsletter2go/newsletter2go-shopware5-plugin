<?php

namespace Shopware\Components\Api\Resource;

/**
 * Class ArticleUnitName
 * @package Shopware\Components\Api\Resource
 */
class ArticleUnitName extends Resource
{

    /**
     * Retrieves unit name
     *
     * @param string $id
     * @param string $shopId
     * @return string|bool
     */
    public function getArticleUnitName($id, $shopId)
    {
        $this->checkPrivilege('read');
        $unitName = false;

        $unit = Shopware()->Models()->find('Shopware\Models\Article\Unit', (int)$id);
        if (!empty($unit)) {
            $unitName = $this->getUnitName($unit, $shopId);
        }

        return $unitName;
    }

    /**
     * Selects name based on unit id
     *
     * @param \Shopware\Models\Article\Unit $unit
     * @param string $shopId
     * @return string
     */
    protected function getUnitName($unit, $shopId)
    {
        $shop = Shopware()->Models()->find('Shopware\Models\Shop\Shop', (int)$shopId);
        $unitName = $unit->getName();

        if (!empty($shop)) {
            $translation = new Translation();
            $translator = $translation->getTranslationComponent();

            $unitTranslation = $translator->read($shopId, 'config_units', 1);

            if (!empty($unitTranslation[$unit->getId()]['description'])) {
                $unitName = $unitTranslation[$unit->getId()]['description'];
            }
        }

        return $unitName;
    }
}

