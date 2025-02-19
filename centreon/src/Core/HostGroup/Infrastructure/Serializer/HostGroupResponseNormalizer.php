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

use Core\HostGroup\Application\UseCase\FindHostGroups\Response\HostGroupResponse;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

final class HostGroupResponseNormalizer implements NormalizerInterface
{
    public function __construct(
        private readonly ObjectNormalizer $normalizer,
    ) {
    }

    public function supportsNormalization(mixed $data, ?string $format = null)
    {
        return $data instanceof HostGroupResponse;
    }

    /**
     * @param HostGroupResponse $object
     * @param string|null $format
     * @param array<string, mixed> $context
     *
     * @throws \Throwable
     *
     * @return array<string, mixed>
     */
    public function normalize(mixed $object, ?string $format = null, array $context = [])
    {
        /** @var array<string, bool|float|int|string> $data */
        $data = $this->normalizer->normalize($object, $format, $context);

        if (isset($data['alias']) && $data['alias'] === '') {
            $data['alias'] = null;
        }
        if (isset($data['notes']) && $data['notes'] === '') {
            $data['notes'] = null;
        }
        if (isset($data['notes_url']) && $data['notes_url'] === '') {
            $data['notes_url'] = null;
        }
        if (isset($data['action_url']) && $data['action_url'] === '') {
            $data['action_url'] = null;
        }
        if (isset($data['comment']) && $data['comment'] === '') {
            $data['comment'] = null;
        }

        return $data;
    }
}
