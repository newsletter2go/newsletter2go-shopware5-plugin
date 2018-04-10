<?php

namespace Shopware\Components\Api\Resource;

use Shopware\Components\Model\QueryBuilder;
use Shopware\Models\Article\Image;
use Shopware\Models\Media\Album;

/**
 * Class ArticleMediaFiles
 * @package Shopware\Components\Api\Resource
 */
class ArticleMediaFiles extends Resource
{
    const COMPARE_VERSION = '5.1.0';

    /**
     * Retrieves the list of article\'s media files
     *
     * @param $id
     *
     * @return array
     *
     * @throws \Shopware\Components\Api\Exception\PrivilegeException
     */
    public function getArticleMediaFiles($id)
    {
        $this->checkPrivilege('read');
        $mediaPath = Shopware()->Modules()->System()->sPathArticleImg;
        $mediaService = Shopware()->Container()->get('shopware_media.media_service');
        $version = \Shopware::VERSION;
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
                $img = $imagePath . '.' . $imageExt;

                $data['images'][] = version_compare($version, self::COMPARE_VERSION) >= 0 ?
                    $mediaService->getUrl('media/image/' . $img) : $mediaPath . $img;

                foreach ($thumbnailSizes as $ts) {
                    $data['thumbnails'][] = version_compare($version, self::COMPARE_VERSION) >= 0 ?
                        $mediaService->getUrl('media/image/thumbnail/' . $imagePath . "_$ts." . $imageExt) :
                        $mediaPath . 'thumbnail/' . $imagePath . "_$ts." . $imageExt;

                }
            }
        }

        return $data;
    }

    /**
     * Returns thumbnails sizes
     *
     * @return array
     */
    public function getArticleThumbnailSizes()
    {
        /** @var Album $album */
        $album = $this->getManager()->getRepository(Album::class)->find(-1);

        return $album->getSettings()->getThumbnailSize();
    }

    /**
     * Selects all images of the main variant of the passed article id.
     * The images are sorted by their position value.
     *
     * @param $articleId
     *
     * @return array
     */
    protected function getArticleImages($articleId)
    {
        $builder = $this->getManager()->createQueryBuilder();
        $builder->select(array('images'))
            ->from(Image::class, 'images')
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
     * @param QueryBuilder $builder
     *
     * @return array
     */
    private function getFullResult(QueryBuilder $builder)
    {
        $query = $builder->getQuery();
        $query->setHydrationMode($this->getResultMode());
        $paginator = $this->getManager()->createPaginator($query);

        return $paginator->getIterator()->getArrayCopy();
    }
    
}
