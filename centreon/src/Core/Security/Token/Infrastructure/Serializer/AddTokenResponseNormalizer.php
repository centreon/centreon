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

use Core\Common\Domain\ResponseCodeEnum;
use Core\Security\Token\Application\UseCase\AddToken\AddTokenResponse;
use Core\Security\Token\Domain\Model\TokenTypeEnum;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class AddTokenResponseNormalizer implements NormalizerInterface
{
    public function __construct(private readonly ObjectNormalizer $normalizer)
    {
    }
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
        $response = $this->normalizer->normalize($object->getData()->apiToken);
        if (array_key_exists('user_id', $response) && array_key_exists('user_name', $response)) {
            $response['user'] = [
                'id' => $response['user_id'],
                'name' => $response['user_name']
            ];
            unset($response['user_id'], $response['user_name']);
        }
        if (array_key_exists('creator_id', $response) && array_key_exists('creator_name', $response)) {
            $response['creator'] = [
                'id' => $response['creator_id'],
                'name' => $response['creator_name']
            ];
            unset($response['creator_id'], $response['creator_name']);
        }
        $response['type'] = $object->getData()->apiToken->getType() === TokenTypeEnum::CMA ? 'cma' : 'api';
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

    /**
     * Convert ResponseCodeEnum to HTTP Status Code.
     *
     * @param ResponseCodeEnum $code
     *
     * @return int
     */
    private function enumToHttpStatusCodeConverter(ResponseCodeEnum $code): int
    {
        return match ($code) {
            ResponseCodeEnum::OK => Response::HTTP_NO_CONTENT,
            ResponseCodeEnum::NotFound => Response::HTTP_NOT_FOUND,
            ResponseCodeEnum::Error => Response::HTTP_INTERNAL_SERVER_ERROR
        };
    }
}