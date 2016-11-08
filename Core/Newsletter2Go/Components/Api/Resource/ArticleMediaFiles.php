<?php

namespace Shopware\Components\Api\Resource;

/**
 * Class ArticleMediaFiles
 * @package Shopware\Components\Api\Resource
 */
class ArticleMediaFiles extends Resource
{

    /**
     * Retrieves the list of article\'s media files
     *
     * @param $id
     * @return array
     */
    public function getArticleMediaFiles($id)
    {
        $this->checkPrivilege('read');
        $mediaPath = Shopware()->Modules()->System()->sPathArticleImg;
        $thumbnailSizes = $this->getArticleThumbnailSizes();
        $data = array(
            'images' => array(),
            'thumbnails' => array(),
        );
        
        $images = $this->getArticleImages($id);

        if (!empty($images)) {

            foreach ($images as $key => $image) {
                $imagePath = $image['path'];
                $imageExt = $image['extension'];
                $data['images'][] = $mediaPath . $imagePath . '.' . $imageExt;

                foreach ($thumbnailSizes as $ts) {
                    $data['thumbnails'][] = $mediaPath . 'thumbnail/' . $imagePath . "_$ts." . $imageExt;
                }
            }
        }


        return $data;
    }

    /**
     * Returns thumbnails sizes
     * @return array
     */
    public function getArticleThumbnailSizes()
    {
        /**
         * @var \Shopware\Models\Media\Album $album
         */
        $album = $this->getManager()->getRepository('Shopware\Models\Media\Album')->find(-1);

        return $album->getSettings()->getThumbnailSize();
    }

    /**
     * Selects all images of the main variant of the passed article id.
     * The images are sorted by their position value.
     *
     * @param $articleId
     * @return array
     */
    protected function getArticleImages($articleId)
    {
        $builder = $this->getManager()->createQueryBuilder();
        $builder->select(array('images'))
            ->from('Shopware\Models\Article\Image', 'images')
            ->innerJoin('images.article', 'article')
            ->where('article.id = :articleId')
            ->orderBy('images.position', 'ASC')
            ->andWhere('images.parentId IS NULL')
            ->setParameters(array('articleId' => $articleId));

        return $this->getFullResult($builder);
    }

    /**
     * Helper function to prevent duplicate source code
     * to get the full query builder result for the current resource result mode
     * using the query paginator.
     *
     * @param $builder
     * @return array
     */
    private function getFullResult(\Shopware\Components\Model\QueryBuilder $builder)
    {
        $query = $builder->getQuery();
        $query->setHydrationMode($this->getResultMode());
        $paginator = $this->getManager()->createPaginator($query);
        return $paginator->getIterator()->getArrayCopy();
    }
    
}
