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

namespace Core\Resources\Infrastructure\API\CountResources;

use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Class
 *
 * @class CountResourcesInputDenormalizer
 * @package Core\Resources\Infrastructure\API\CountResources
 */
class CountResourcesInputDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;
    private const ALREADY_CALL = self::class . 'already_called';

    /**
     * @param mixed $data
     * @param string $type
     * @param string|null $format
     * @param array<string,mixed> $context
     *
     * @throws ExceptionInterface
     * @return CountResourcesInput
     */
    public function denormalize(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = []
    ): CountResourcesInput {
        $context[self::ALREADY_CALL] = true;

        if (isset($data['all_pages']) && $data['all_pages'] !== '') {
            $data['all_pages']
                = filter_var($data['all_pages'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                ?? $data['all_pages'];
        }

        if (isset($data['page'])) {
            $data['page']
                = filter_var($data['page'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE)
                ?? $data['page'];
        }

        if (isset($data['limit'])) {
            $data['limit']
                = filter_var($data['limit'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE)
                ?? $data['limit'];
        }

        return $this->denormalizer->denormalize($data, $type, $format, $context);
    }

    /**
     * @param mixed $data
     * @param string $type
     * @param string|null $format
     * @param array<string,mixed> $context
     *
     * @return bool
     */
    public function supportsDenormalization(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = [],
    ): bool {
        if ($context[self::ALREADY_CALL] ?? false) {
            return false;
        }

        return $type === CountResourcesInput::class && is_array($data);
    }

    /**
     * @param ?string $format
     * @return array<class-string|'*'|'object'|string, bool|null>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            CountResourcesInput::class => false,
        ];
    }
}
