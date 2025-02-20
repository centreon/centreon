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

declare(strict_types = 1);

namespace Core\User\Infrastructure\API\FindUserPermissions;

use Core\User\Application\UseCase\FindUserPermissions\FindUserPermissionsResponse;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

final class FindUserPermissionsResponseNormalizer implements NormalizerInterface
{
    public function __construct(private ObjectNormalizer $normalizer)
    {
    }

    /**
     * @param mixed $object
     * @param string|null $format
     * @param array<string, mixed> $context
     *
     * @throws ExceptionInterface
     *
     * @return array<string, bool>
     */
    public function normalize(
        mixed $object,
        ?string $format = null,
        array $context = []
    ): array
    {
        $data = $this->normalizer->normalize($object, $format, $context);
        $normalizedData = [];
        if (! isset($data['permissions'])) {
            throw new \InvalidArgumentException('Normalized data missing, required fields: permissions');
        }

        if (is_iterable($data['permissions'])) {
            foreach ($data['permissions'] as $permission) {
                $name = (string) key($permission);
                $normalizedData[$name] = (bool) $permission[$name];
            }
        }

        return $normalizedData;
    }

    public function supportsNormalization(mixed $data, ?string $format = null): bool
    {
        return $data instanceof FindUserPermissionsResponse;
    }
}
