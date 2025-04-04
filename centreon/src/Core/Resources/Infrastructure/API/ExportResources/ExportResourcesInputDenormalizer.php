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

use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Class
 *
 * @class ExportResourcesInputDenormalizer
 * @package Core\Resources\Infrastructure\API\ExportResources
 */
class ExportResourcesInputDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    /**
     * @param mixed $data
     * @param string $type
     * @param string|null $format
     * @param array $context
     *
     * @throws ExceptionInterface
     * @return ExportResourcesInput
     */
    public function denormalize(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = []
    ): ExportResourcesInput {
        if (isset($data['all_pages'])) {
            $data['all_pages'] =
                filter_var($data['all_pages'], FILTER_VALIDATE_BOOLEAN,FILTER_NULL_ON_FAILURE)
                ?? $data['all_pages'];
        }

        if (isset($data['page'])) {
            $data['page'] =
                filter_var($data['page'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE)
                ?? $data['page'];
        }

        if (isset($data['limit'])) {
            $data['limit'] =
                filter_var($data['limit'], FILTER_VALIDATE_INT,FILTER_NULL_ON_FAILURE)
                ?? $data['limit'];
        }

        if (isset($data['max_lines'])) {
            $data['max_lines'] =
                filter_var($data['max_lines'], FILTER_VALIDATE_INT,FILTER_NULL_ON_FAILURE)
                ?? $data['max_lines'];
        }

        return $this->denormalizer->denormalize($data, $type, $format, ['already_call' => true]);
    }

    /**
     * @param mixed $data
     * @param string $type
     * @param string|null $format
     * @param array $context
     *
     * @return bool
     */
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        if ($context['already_call'] ?? false) {
            return false;
        }
        return $type === ExportResourcesInput::class && is_array($data);
    }
}
