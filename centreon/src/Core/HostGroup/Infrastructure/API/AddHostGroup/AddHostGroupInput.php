<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
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

namespace Core\HostGroup\Infrastructure\API\AddHostGroup;

use Core\Common\Domain\PlatformType;
use Core\Common\Infrastructure\Validator\Constraints\WhenPlatform;
use Symfony\Component\Validator\Constraints as Assert;

final class AddHostGroupInput
{
    /**
     * @param string $name
     * @param string|null $alias
     * @param string|null $geoCoords
     * @param string|null $comment
     * @param int|null $iconId
     * @param int[] $hosts
     * @param int[] $resourceAccessRules
     */
    public function __construct(
        #[Assert\NotNull()]
        #[Assert\Type('string')]
        public readonly mixed $name,

        #[Assert\Type('string')]
        public readonly mixed $alias,

        #[Assert\Type('string')]
        public readonly mixed $geoCoords,

        #[Assert\Type('string')]
        public readonly mixed $comment,

        /**
         * This field MUST NOT be used outside of a ON PREM Platform Context.
         */
        #[WhenPlatform(PlatformType::ON_PREM, [
            new Assert\Type('integer'),
        ])]
        public readonly mixed $iconId,

        #[Assert\NotNull()]
        #[Assert\Type('array')]
        #[Assert\All(
            new Assert\Type('integer')
        )]
        public readonly mixed $hosts,

        /**
         * This field MUST NOT be used outside of a CLOUD Platform Context.
         */
        #[WhenPlatform(PlatformType::CLOUD, [
            new Assert\NotNull(),
            new Assert\Type('array'),
            new Assert\All(
                new Assert\Type('integer')
            ),
        ])]
        public readonly mixed $resourceAccessRules,
    ) {
    }
}
