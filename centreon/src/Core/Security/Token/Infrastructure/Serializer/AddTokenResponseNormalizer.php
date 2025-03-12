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

namespace Core\Security\Token\Infrastructure\Serializer;

use Core\Security\Token\Application\UseCase\AddToken\AddTokenResponse;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class AddTokenResponseNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    /**
     * @param AddTokenResponse $object
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
        $response = $this->normalizer->normalize($object->getData()->apiToken, $format, $context);
        $response['token'] = $object->getData()->token;

        return $response;
    }

    /**
     * @inheritDoc
     */
    public function supportsNormalization(mixed $data, ?string $format = null): bool
    {
        return $data instanceof AddTokenResponse;
    }
}
