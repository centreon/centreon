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

declare(strict_types=1);

namespace Core\Infrastructure\Common\Api;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ListingResponseInterface;
use Core\Application\Common\UseCase\StandardPresenterInterface;
use Core\Application\Common\UseCase\StandardResponseInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

class StandardPresenter implements StandardPresenterInterface
{
    /**
     * @param Serializer $serializer
     * @param RequestParametersInterface $requestParameters
     */
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly RequestParametersInterface $requestParameters,
    ) {
    }

    /**
     * @param StandardResponseInterface $data
     * @param string $format
     * @param array<string, mixed> $context
     *
     * @throws ExceptionInterface
     *
     * @return string
     */
    public function present(
        StandardResponseInterface $data,
        array $context = [],
        string $format = JsonEncoder::FORMAT,
    ): string {
        if ($data instanceof ListingResponseInterface) {
            return $this->serializer->serialize(
                [
                    'result' => $data->getData(),
                    'meta' => $this->requestParameters->toArray(),
                ],
                $format,
                $context,
            );
        }

        return $this->serializer->serialize($data->getData(), $format, $context);
    }
}
