<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Core\Dashboard\Infrastructure\Serializer;

use Core\Dashboard\Application\UseCase\FindDashboardContacts\Response\ContactsResponseDto;
use Core\Dashboard\Infrastructure\Model\DashboardGlobalRoleConverter;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

final class DashboardContactResponseNormalizer implements NormalizerInterface
{
    public function __construct(
        private readonly ObjectNormalizer $normalizer,
    ) {
    }

    public function supportsNormalization(mixed $data, ?string $format = null): bool
    {
        return $data instanceof ContactsResponseDto;
    }

    /**
     * @param ContactsResponseDto $object
     * @param string|null $format
     * @param array<string, mixed> $context
     *
     * @throws \Throwable
     *
     * @return array{id: int, name: string, email: string, most_permissive_role: string}
     */
    public function normalize(mixed $object, ?string $format = null, array $context = [])
    {
        /** @var array{
         *  id: int,
         *  name: string,
         *  email: string,
         *  most_permissive_role: string
         * } $data
         */
        $data = $this->normalizer->normalize($object, $format, $context);

        $data['most_permissive_role'] = DashboardGlobalRoleConverter::toString($object->mostPermissiveRole) === 'creator'
            ? 'editor'
            : DashboardGlobalRoleConverter::toString($object->mostPermissiveRole);

        return $data;
    }
}

