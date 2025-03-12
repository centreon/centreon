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

use Core\Security\Token\Domain\Model\Token;
use Core\Security\Token\Domain\Model\TokenTypeEnum;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class TokenNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;
    private const ALREADY_CALLED = 'USER_NORMALIZER_ALREADY_CALLED';

    /**
     * @param Token $object
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
        $context[self::ALREADY_CALLED] = true;
        /** @var array<string, mixed> $response */
        $response = $this->normalizer->normalize($object, $format, $context);

        if (array_key_exists('user_id', $response) && array_key_exists('user_name', $response)) {
            $response['user'] = [
                'id' => $response['user_id'],
                'name' => $response['user_name'],
            ];
            unset($response['user_id'], $response['user_name']);
        }
        if (array_key_exists('creator_id', $response) && array_key_exists('creator_name', $response)) {
            $response['creator'] = [
                'id' => $response['creator_id'],
                'name' => $response['creator_name'],
            ];
            unset($response['creator_id'], $response['creator_name']);
        }
        $response['type'] = $this->enumToTypeConverter($object->getType());

        return $response;
    }

    /**
     * @inheritDoc
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        return $data instanceof Token;
    }

    /**
     * Convert TokenTypeEnum to string value.
     *
     * @param TokenTypeEnum $code
     *
     * @return string
     */
    private function enumToTypeConverter(TokenTypeEnum $code): string
    {
        return match ($code) {
            TokenTypeEnum::CMA => 'cma',
            TokenTypeEnum::API => 'api',
        };
    }
}