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

use Core\User\Domain\Model\Permission;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final readonly class PermissionNormalizer implements NormalizerInterface
{
    public function __construct(
        #[Autowire(service: 'serializer.normalizer.object')]
        private readonly NormalizerInterface $normalizer,
    ) {
    }

    /**
     * @param mixed $object
     * @param string|null $format
     * @param array<string, mixed> $context
     *
     * @throws ExceptionInterface
     * @return array<string, mixed>
     */
    public function normalize(
        mixed $object,
        ?string $format = null,
        array $context = []
    ): array {
        $data = $this->normalizer->normalize($object, $format, $context);
        if (! isset($data['name'], $data['is_active'])) {
            throw new \InvalidArgumentException('Normalized data missing, required fields: name, is_active');
        }

        return [$data['name'] => $data['is_active']];
    }

    /**
     * @param array<string, mixed> $context
     * @param mixed $data
     * @param ?string $format
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Permission;
    }

    /**
     * @param ?string $format
     * @return array<class-string|'*'|'object'|string, bool|null>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            Permission::class => true,
        ];
    }
}
