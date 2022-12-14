<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
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

namespace Core\Infrastructure\Common\Presenter;

use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\{
    BodyResponseInterface, CreatedResponse, ErrorResponse, InvalidArgumentResponse, ResponseStatusInterface,
    UnauthorizedResponse, PaymentRequiredResponse, ForbiddenResponse, NoContentResponse, NotFoundResponse
};
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class JsonFormatter implements PresenterFormatterInterface
{
    use LoggerTrait;

    /**
     * {@inheritDoc}
     *
     * @throws \InvalidArgumentException
     * @throws \TypeError
     */
    public function format(mixed $data, array $headers): JsonResponse
    {
        if (is_object($data)) {
            switch (true) {
                case is_a($data, NotFoundResponse::class):
                    $this->debug('Data not found. Generating a not found response');

                    return $this->generateJsonErrorResponse($data, Response::HTTP_NOT_FOUND, $headers);
                case is_a($data, ErrorResponse::class):
                    $this->debug('Data error. Generating an error response');

                    return $this->generateJsonErrorResponse($data, Response::HTTP_INTERNAL_SERVER_ERROR, $headers);
                case is_a($data, InvalidArgumentResponse::class):
                    $this->debug('Invalid argument. Generating an error response');

                    return $this->generateJsonErrorResponse($data, Response::HTTP_BAD_REQUEST, $headers);
                case is_a($data, UnauthorizedResponse::class):
                    $this->debug('Unauthorized. Generating an error response');

                    return $this->generateJsonErrorResponse($data, Response::HTTP_UNAUTHORIZED, $headers);
                case is_a($data, PaymentRequiredResponse::class):
                    $this->debug('Payment required. Generating an error response');

                    return $this->generateJsonErrorResponse($data, Response::HTTP_PAYMENT_REQUIRED, $headers);
                case is_a($data, ForbiddenResponse::class):
                    $this->debug('Forbidden. Generating an error response');

                    return $this->generateJsonErrorResponse($data, Response::HTTP_FORBIDDEN, $headers);
                case is_a($data, CreatedResponse::class):
                    return $this->generateJsonResponse($data, Response::HTTP_CREATED, $headers);
                case is_a($data, NoContentResponse::class):
                    return $this->generateJsonResponse(null, Response::HTTP_NO_CONTENT, $headers);
                default:
                    return $this->generateJsonResponse($data, Response::HTTP_OK, $headers);
            }
        }
        return $this->generateJsonResponse($data, Response::HTTP_OK, $headers);
    }

    /**
     * Generates json response with error message and http code
     *
     * @param mixed $data
     * @param int $code
     * @param array<string, mixed> $headers
     *
     * @return JsonResponse
     *
     * @throws \InvalidArgumentException
     * @throws \TypeError
     */
    private function generateJsonErrorResponse(mixed $data, int $code, array $headers): JsonResponse
    {
        $errorData = $this->formatErrorContent($data, $code);

        return $this->generateJsonResponse($errorData, $code, $headers);
    }

    /**
     * @param mixed $data
     * @param int $code
     * @param array<string, mixed> $headers
     *
     * @return JsonResponse
     *
     * @throws \InvalidArgumentException
     * @throws \TypeError
     */
    private function generateJsonResponse(mixed $data, int $code, array $headers): JsonResponse
    {
        if (is_object($data)) {
            if (is_a($data, \Generator::class)) {
                $data = iterator_to_array($data);
            } elseif (is_a($data, CreatedResponse::class)) {
                /**
                 * @var CreatedResponse $data
                 */
                $data = $data->getPayload();
            }
        }
        return new JsonResponse($data, $code, $headers);
    }

    /**
     * Format content on error
     *
     * @param mixed $data
     * @param integer $code
     * @return mixed[]|null
     */
    protected function formatErrorContent(mixed $data, int $code): ?array
    {
        $content = null;
        if (is_object($data) && is_a($data, ResponseStatusInterface::class)) {
            $content = [
                'code' => $code,
                'message' => $data->getMessage(),
            ];
            if (is_a($data, BodyResponseInterface::class) && is_array($data->getBody())) {
                $content = array_merge($content, $data->getBody());
            }
        }
        return $content;
    }
}
