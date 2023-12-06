<?php
/************************************************************************
 *
 * Copyright 2023 Adobe
 * All Rights Reserved.
 *
 * NOTICE: All information contained herein is, and remains
 * the property of Adobe and its suppliers, if any. The intellectual
 * and technical concepts contained herein are proprietary to Adobe
 * and its suppliers and are protected by all applicable intellectual
 * property laws, including trade secret and copyright laws.
 * Dissemination of this information or reproduction of this material
 * is strictly forbidden unless prior written permission is obtained
 * from Adobe.
 * ************************************************************************
 */
declare(strict_types=1);

namespace Magento\Catalog\Pricing\Price;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Collection\AbstractCollection;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Cache\FrontendInterface;
use Magento\Framework\EntityManager\MetadataPool;

class SpecialPriceBulkResolver implements SpecialPriceBulkResolverInterface
{
    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resource;

    /**
     * @var MetadataPool
     */
    private MetadataPool $metadataPool;

    /**
     * @var FrontendInterface
     */
    private FrontendInterface $cache;

    /**
     * @var int
     */
    private int $cacheLifeTime;

    /**
     * @param ResourceConnection $resource
     * @param MetadataPool $metadataPool
     * @param FrontendInterface $cache
     * @param int $cacheLifeTime
     */
    public function __construct(
        ResourceConnection $resource,
        MetadataPool $metadataPool,
        FrontendInterface $cache,
        int $cacheLifeTime = self::DEFAULT_CACHE_LIFE_TIME
    ) {
        $this->resource = $resource;
        $this->metadataPool = $metadataPool;
        $this->cache = $cache;
        $this->cacheLifeTime = $cacheLifeTime;
    }

    /**
     * Determines if blocks have special prices
     *
     * @param int $storeId
     * @param AbstractCollection|null $productCollection
     * @return array
     * @throws \Exception
     */
    public function generateSpecialPriceMap(int $storeId, ?AbstractCollection $productCollection): array
    {
        if (!$productCollection) {
            return [];
        }
        //$cacheKey = $this->getCacheKey($storeId, $productCollection);
        //$cachedData = $this->getCachedData($cacheKey);
        if (true || $cachedData === null) {
            $metadata = $this->metadataPool->getMetadata(ProductInterface::class);
            $connection = $this->resource->getConnection();
            $select = $connection->select()
                ->from(
                    ['e' => $this->resource->getTableName('catalog_product_entity')]
                )
                ->joinLeft(
                    ['link' => $this->resource->getTableName('catalog_product_super_link')],
                    'link.parent_id = e.' . $metadata->getLinkField()
                )
                ->joinLeft(
                    ['product_website' => $this->resource->getTableName('catalog_product_website')],
                    'product_website.product_id = link.product_id'
                )
                ->joinLeft(
                    ['price' => $this->resource->getTableName('catalog_product_index_price')],
                    'price.entity_id = COALESCE(link.product_id, e.entity_id) AND price.website_id = ' . $storeId .
                    ' AND price.customer_group_id = 0'
                )
                ->where('e.entity_id IN (' . implode(',', $productCollection->getAllIds()) . ')')
                ->columns(
                    [
                        'link.product_id',
                        '(price.final_price < price.price) AS hasSpecialPrice',
                        'e.' . $metadata->getLinkField() . ' AS identifier',
                        'e.entity_id'
                    ]
                );
            $data = $connection->fetchAll($select);
            $map = [];
            foreach ($data as $specialPriceInfo) {
                if (!isset($map[$specialPriceInfo['entity_id']])) {
                    $map[$specialPriceInfo['entity_id']] = (bool) $specialPriceInfo['hasSpecialPrice'];
                } else {
                    if ($specialPriceInfo['hasSpecialPrice'] > $map[$specialPriceInfo['entity_id']]) {
                        $map[$specialPriceInfo['entity_id']] = true;
                    }
                }

            }
            //$this->saveCachedData($cacheKey, $map, array_column($data, 'identifier'));

            return $map;
        }

        return $cachedData;
    }

    /**
     * Generate cache key
     *
     * @param int $storeId
     * @param AbstractCollection $productCollection
     * @return string
     */
    private function getCacheKey(int $storeId, AbstractCollection $productCollection): string
    {
        $keyParts = $productCollection->getAllIds();
        $keyParts[] = 'store_id_' . $storeId;

        return hash('sha256', implode('_', $keyParts));
    }

    /**
     * Retrieve potential cached data
     *
     * @param string $cacheKey
     * @return array|null
     */
    private function getCachedData(string $cacheKey): ?array
    {
        $data = $this->cache->load($cacheKey);
        if (!$data) {
            return null;
        }

        return json_decode($data, true);
    }

    /**
     * Store data in cache
     *
     * @param string $cacheKey
     * @param array $data
     * @param array $productTags
     * @return bool
     */
    private function saveCachedData(string $cacheKey, array $data, array $productTags): bool
    {
        $tags = [
            Category::CACHE_TAG,
            Product::CACHE_TAG,
            'price'
        ];
        $productTags = array_unique($productTags);
        foreach ($productTags as $tag) {
            $tags[] = Product::CACHE_TAG . '_' . $tag;
        }

        return $this->cache->save(json_encode($data), $cacheKey, $tags, $this->cacheLifeTime);
    }
}
