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

namespace Core\HostGroup\Infrastructure\Serializer;

use Core\Common\Domain\SimpleEntity;
use Core\HostGroup\Domain\Model\HostGroupRelation;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class HostGroupRelationNormalizer implements NormalizerInterface
{
    public function __construct(private readonly ObjectNormalizer $normalizer)
    {
    }

    /**
     * @param HostGroupRelation $object
     * @param string|null $format
     * @param array<string, mixed> $context
     *
     * @throws \Throwable
     *
     * @return array<string, mixed>
     */
    public function normalize(
        mixed $object,
        ?string $format = null,
        array $context = []
    ): array {
        /**
         * @var array<string, bool|float|int|string> $data
         * @var array{groups?: string[],is_cloud_platform: bool} $context
         */
        $data = $this->normalizer->normalize(
            object: $object->getHostGroup(),
            context: $context
        );

        if (isset($data['alias']) && $data['alias'] === '') {
            $data['alias'] = null;
        }
        if (isset($data['comment']) && $data['comment'] === '') {
            $data['comment'] = null;
        }

        $data['hosts'] = array_map(
            fn (SimpleEntity $host) => $this->normalizer->normalize($host, $format),
            $object->getHosts()
        );

        if ($context['is_cloud_platform'] === true) {
            $data['resource_access_rules'] = [];
            foreach ($object->getResourceAccessRules() as $resourceAccessRule) {
                $data['resource_access_rules'][] = $this->normalizer->normalize(
                    object: $resourceAccessRule,
                    context: $context
                );
            }
        }

        return $data;
    }

    /**
     * @inheritDoc
     */
    public function supportsNormalization(mixed $data, ?string $format = null): bool
    {
        return $data instanceof HostGroupRelation;
    }
}
