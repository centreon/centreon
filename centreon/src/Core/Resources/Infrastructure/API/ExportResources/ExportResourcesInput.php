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

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class
 *
 * @class ExportResourcesInput
 * @package Core\Resources\Infrastructure\API\ExportResources
 */
class ExportResourcesInput {

    /**
     * ExportResourcesInput constructor
     *
     * @param mixed $format
     * @param mixed $all_pages
     * @param mixed $columns
     * @param mixed $max_lines
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\NotNull]
        #[Assert\Type('string')]
        #[Assert\Choice(
            choices: ['csv'],
            message: 'The format must be one of the following: csv'
        )]
        public string $format,
        #[Assert\NotBlank]
        #[Assert\NotNull]
        #[Assert\Type('boolean')]
        public bool $all_pages,
        #[Assert\NotBlank]
        #[Assert\NotNull]
        public array $columns = [],
        #[Assert\Type('integer')]
        #[Assert\Length(min: 1, max: 10000)]
        public int $max_lines,
    ) {
    }

}
