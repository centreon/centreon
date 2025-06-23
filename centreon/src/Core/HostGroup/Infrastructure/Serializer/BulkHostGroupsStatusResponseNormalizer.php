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

use Centreon\Domain\Log\LoggerTrait;
use Core\Common\Domain\ResponseCodeEnum;
use Core\HostGroup\Application\UseCase\DeleteHostGroups\DeleteHostGroupsStatusResponse;
use Core\HostGroup\Application\UseCase\DuplicateHostGroups\DuplicateHostGroupsStatusResponse;
use Core\HostGroup\Application\UseCase\EnableDisableHostGroups\EnableDisableHostGroupsStatusResponse;
use Core\Infrastructure\Common\Api\Router;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class BulkHostGroupsStatusResponseNormalizer implements NormalizerInterface
{
    use LoggerTrait;
    private const HOSTGROUP_ROUTE_NAME = 'GetHostGroup';

    /**
     * @param Router $router
     */
    public function __construct(
        private readonly Router $router
    ) {
    }

    /**
     * @param DeleteHostGroupsStatusResponse $object
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
        return [
            'href' => $this->router->generate(self::HOSTGROUP_ROUTE_NAME, ['hostGroupId' => $object->id]),
            'status' => $this->enumToHttpStatusCodeConverter($object->status),
            'message' => $object->message,
        ];
    }

    /**
     * @inheritDoc
     */
    public function supportsNormalization(mixed $data, ?string $format = null): bool
    {
        return $data instanceof DeleteHostGroupsStatusResponse
            || $data instanceof DuplicateHostGroupsStatusResponse
            || $data instanceof EnableDisableHostGroupsStatusResponse;
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
