<?php

namespace Newsletter2Go\Components;

use Shopware\Models\Category\Category;

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

    /**
     * Helper function which selects last category of the passed
     * article id.
     * This function returns only the directly assigned categories.
     * To prevent a big data, this function selects only the category name and id.
     *
     * @param integer $articleId
     * @return string
     */
    public function getArticleCategories($articleId)
    {
        $em = Shopware()->Models();
        $categoryName = '';
        $builder = $em->createQueryBuilder();
        $builder->select(array('categories'))
            ->from(Category::class, 'categories', 'categories.id')
            ->innerJoin('categories.articles', 'articles')
            ->where('articles.id = :articleId')
            ->setParameter('articleId', $articleId);

        $query = $builder->getQuery();
        $paginator = $em->createPaginator($query);
        /** @var Category[] $categories */
        $categories = $paginator->getIterator()->getArrayCopy();
        foreach ($categories as $category) {
            $categoryName = $category->getName();
        }

        return $categoryName;
    }
}
