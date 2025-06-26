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
use Core\HostGroup\Application\UseCase\FindHostGroups\HostGroupResponse;
use Core\HostGroup\Application\UseCase\GetHostGroup\GetHostGroupResponse;
use Core\Infrastructure\Common\Api\HttpUrlTrait;
use Core\ResourceAccess\Domain\Model\TinyRule;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class HostGroupNormalizer implements NormalizerInterface
{
    use HttpUrlTrait;

    public function __construct(
        private readonly string $mediaPath,
        #[Autowire(service: 'serializer.normalizer.object')]
        private readonly NormalizerInterface $normalizer
    ) {
    }

    /**
     * @param array<string, mixed> $context
     * @param mixed $data
     * @param ?string $format
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof HostGroupResponse
        || $data instanceof GetHostGroupResponse;
    }

    /**
     * @param GetHostGroupResponse|HostGroupResponse $object
     * @param string|null $format
     * @param array<string, mixed> $context
     *
     * @throws \Throwable
     *
     * @return array<string, mixed>
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        /**
         * @var array<string, bool|float|int|string> $data
         * @var array{groups?: string[],is_cloud_platform?: bool} $context
         */
        $data = $this->normalizer->normalize($object->hostgroup, $format, $context);

        if (isset($data['alias']) && $data['alias'] === '') {
            $data['alias'] = null;
        }
        if (isset($data['comment']) && $data['comment'] === '') {
            $data['comment'] = null;
        }

        if (in_array('HostGroup:List', $context['groups'] ?? [], true)) {
            /** @var HostGroupResponse $object */
            $data['icon'] = $object->icon !== null
            ? [
                'id' => $object->icon->getId(),
                'name' => $object->icon->getFilename(),
                'url' => $this->generateNormalizedIconUrl($object->icon->getDirectory() . '/' . $object->icon->getFilename()),
            ]
            : null;
            $data['enabled_hosts_count'] = $object->hostsCount
                ? $object->hostsCount->getEnabledHostsCount()
                : 0;
            $data['disabled_hosts_count'] = $object->hostsCount
                ? $object->hostsCount->getDisabledHostsCount()
                : 0;
        }
        if (in_array('HostGroup:Get', $context['groups'] ?? [], true)) {
            /** @var GetHostGroupResponse $object */
            $data['icon'] = $object->icon !== null
            ? [
                'id' => $object->icon->getId(),
                'name' => $object->icon->getFilename(),
                'url' => $this->generateNormalizedIconUrl($object->icon->getDirectory() . '/' . $object->icon->getFilename()),
            ]
            : null;
            $data['hosts'] = array_map(
                fn (SimpleEntity $host) => $this->normalizer->normalize($host, $format),
                $object->hosts
            );

            if (true === ($context['is_cloud_platform'] ?? false)) {
                $data['resource_access_rules'] = array_map(
                    fn (TinyRule $rule) => $this->normalizer->normalize($rule, $format, $context),
                    $object->rules
                );
            }
        }

        return $data;
    }

    /**
     * @param ?string $format
     * @return array<class-string|'*'|'object'|string, bool|null>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            HostGroupResponse::class => true,
            GetHostGroupResponse::class => true,
        ];
    }

    /**
     * @param string|null $url
     *
     * @return string|null
     */
    private function generateNormalizedIconUrl(?string $url): ?string
    {
        return $url !== null
            ? $this->getBaseUri() . '/' . $this->mediaPath . '/' . $url
            : $url;
    }
}
