<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\Elasticsearch7\Model\Adapter\DynamicTemplates;

/**
 * @inheridoc
 * @deprecated because of EOL for Elasticsearch7
 */
class PositionMapper implements MapperInterface
{
    /**
     * @inheridoc
     */
    public function processTemplates(array $templates): array
    {
        $templates[] = [
            'position_mapping' => [
                'match' => 'position_*',
                'match_mapping_type' => 'string',
                'mapping' => [
                    'type' => 'integer',
                    'index' => true,
                ],
            ],
        ];

        return $templates;
    }
}
