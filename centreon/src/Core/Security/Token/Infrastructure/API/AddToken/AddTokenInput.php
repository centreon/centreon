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

namespace Core\Security\Token\Infrastructure\API\AddToken;

use Core\Common\Infrastructure\Validator\DateFormat;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final readonly class AddTokenInput
{
    public const API_TYPE = 'api';
    public const CMA_TYPE = 'cma';

    /**
     * @param string $name
     * @param ?string $expirationDate
     * @param string $type
     * @param int $userId
     */
    public function __construct(
        #[Assert\NotNull()]
        #[Assert\Type('string')]
        public mixed $name,

        #[Assert\DateTime(
            format: \DateTimeInterface::ATOM,
        )]
        public mixed $expirationDate,

        #[Assert\NotNull()]
        #[Assert\Choice(choices: [self::API_TYPE, self::CMA_TYPE])]
        public mixed $type,

        #[Assert\NotNull()]
        #[Assert\Type('int')]
        public mixed $userId,
    ) {
    }
}