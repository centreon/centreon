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

class ExportResourcesInput {
    public const EXPORT_ALLOWED_FORMAT = ['csv'];
    public const EXPORT_MAX_LINES = 10000;

    /**
     * ExportResourcesInput constructor
     *
     * @param string $format
     * @param bool $allPages
     * @param array<string> $columns
     * @param int $maxLines
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\NotNull]
        #[Assert\Type('string')]
        #[Assert\Choice(
            choices: self::EXPORT_ALLOWED_FORMAT,
            message: 'The format must be one of the following: csv'
        )]
        public mixed $format,
        #[Assert\NotBlank]
        #[Assert\NotNull]
        #[Assert\Type('boolean')]
        public mixed $allPages,
        #[Assert\NotBlank]
        #[Assert\NotNull]
        #[Assert\Sequentially([
            new Assert\Type('array'),
            new Assert\All(
                [new Assert\Type('string')]
            ),
        ])]
        public mixed $columns = [],
        #[Assert\Type('integer')]
        #[Assert\Length(min: 1, max: self::EXPORT_MAX_LINES)]
        public mixed $maxLines,
    ) {
    }

}
