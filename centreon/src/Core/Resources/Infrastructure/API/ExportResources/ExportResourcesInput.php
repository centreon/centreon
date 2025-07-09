<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

declare(strict_types=1);

namespace Core\Resources\Infrastructure\API\ExportResources;

use Core\Resources\Application\UseCase\ExportResources\Enum\AllowedFormatEnum;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ExportResourcesInput
{
    public const EXPORT_MAX_LINES = 10000;
    public const EXPORT_ALLOWED_COLUMNS = [
        'resource',
        'status',
        'parent_resource',
        'duration',
        'last_check',
        'information',
        'tries',
        'severity',
        'notes_url',
        'action_url',
        'state',
        'alias',
        'parent_alias',
        'fqdn',
        'monitoring_server_name',
        'notification',
        'checks',
    ];

    public function __construct(
        #[Assert\NotBlank(message: 'format parameter is required')]
        #[Assert\Choice(
            callback: [AllowedFormatEnum::class, 'values'],
            message: 'format parameter must be one of the following: {{ choices }}'
        )]
        public mixed $format,
        #[Assert\NotNull(message: 'all_pages parameter is required')]
        #[Assert\Type('bool', message: 'all_pages parameter must be a boolean')]
        public mixed $allPages,
        #[Assert\Type('array', message: 'columns parameter must be an array')]
        #[Assert\All([
            new Assert\Type('string', message: 'columns parameter must be an array of strings'),
            new Assert\NotBlank(message: 'columns parameter value must not be empty'),
            new Assert\Choice(
                choices: self::EXPORT_ALLOWED_COLUMNS,
                message: 'columns parameter must be one of the following: {{ choices }}'
            ),
        ])]
        public mixed $columns,
        #[Assert\When(
            expression: 'this.allPages === true',
            constraints: [
                new Assert\Sequentially([
                    new Assert\NotBlank(message: 'max_lines parameter is required when all_pages is true'),
                    new Assert\Type(type: 'int', message: 'max_lines parameter must be an integer'),
                    new Assert\Range(
                        notInRangeMessage: 'max_lines parameter must be between {{ min }} and {{ max }}',
                        min: 1,
                        max: self::EXPORT_MAX_LINES
                    ),
                ]),
            ]
        )]
        public mixed $maxLines,
        #[Assert\When(
            expression: 'this.allPages === false',
            constraints: [
                new Assert\Sequentially([
                    new Assert\NotBlank(message: 'page parameter is required when all_pages is false'),
                    new Assert\Type('int', message: 'page parameter must be an integer'),
                    new Assert\Range(
                        minMessage: 'page parameter must be greater than 1',
                        min: 1
                    ),
                ]),
            ]
        )]
        public mixed $page,
        #[Assert\When(
            expression: 'this.allPages === false',
            constraints: [
                new Assert\NotBlank(message: 'limit parameter is required when all_pages is false'),
                new Assert\Type('int', message: 'limit parameter must be an integer'),
            ]
        )]
        public mixed $limit,
        #[Assert\NotBlank(message: 'sort_by parameter is required')]
        #[Assert\Json(message: 'sort_by parameter must be a valid JSON')]
        public mixed $sort_by,
        #[Assert\NotBlank(message: 'search parameter is required')]
        #[Assert\Json(message: 'search parameter must be a valid JSON')]
        public mixed $search
    ) {}
}
