<?php

/*
 * Copyright 2005 - 2020 Centreon (https://www.centreon.com/)
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

namespace Centreon\Application\Controller;

use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\CreatedResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\MultiStatusResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\NotModifiedResponse;
use Core\Application\Common\UseCase\PaymentRequiredResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Abstraction over the FOSRestController.
 */
abstract class AbstractController extends AbstractFOSRestController
{
    use LoggerTrait;
    public const ROLE_API_REALTIME = 'ROLE_API_REALTIME';
    public const ROLE_API_REALTIME_EXCEPTION_MESSAGE = 'You are not authorized to access this resource';
    public const ROLE_API_CONFIGURATION = 'ROLE_API_CONFIGURATION';
    public const ROLE_API_CONFIGURATION_EXCEPTION_MESSAGE = 'You are not authorized to access this resource';

    public function createResponse(ResponseStatusInterface $response): Response
    {
        $statusCode = match(true) {
            $response instanceof ConflictResponse => Response::HTTP_CONFLICT,
            $response instanceof CreatedResponse => Response::HTTP_CREATED,
            $response instanceof ForbiddenResponse => Response::HTTP_FORBIDDEN,
            $response instanceof InvalidArgumentResponse => Response::HTTP_BAD_REQUEST,
            $response instanceof MultiStatusResponse => Response::HTTP_MULTI_STATUS,
            $response instanceof NoContentResponse => Response::HTTP_NO_CONTENT,
            $response instanceof NotFoundResponse => Response::HTTP_NOT_FOUND,
            $response instanceof NotModifiedResponse => Response::HTTP_NOT_MODIFIED,
            $response instanceof PaymentRequiredResponse => Response::HTTP_PAYMENT_REQUIRED,
            default => Response::HTTP_INTERNAL_SERVER_ERROR
        };

        return match($statusCode) {
            Response::HTTP_CREATED, Response::HTTP_NO_CONTENT, Response::HTTP_NOT_MODIFIED => new JsonResponse(null, $statusCode),
            default => new JsonResponse([
                'code' => $statusCode,
                'message' => $response->getMessage(),
            ], $statusCode)
        };
    }

    /**
     * @throws AccessDeniedException
     */
    public function denyAccessUnlessGrantedForApiConfiguration(): void
    {
        parent::denyAccessUnlessGranted(
            static::ROLE_API_CONFIGURATION,
            null,
            static::ROLE_API_CONFIGURATION_EXCEPTION_MESSAGE
        );
    }

    /**
     * @throws AccessDeniedException
     */
    public function denyAccessUnlessGrantedForApiRealtime(): void
    {
        parent::denyAccessUnlessGranted(
            static::ROLE_API_REALTIME,
            null,
            static::ROLE_API_REALTIME_EXCEPTION_MESSAGE
        );
    }

    /**
     * Get current base uri.
     *
     * @return string
     */
    protected function getBaseUri(): string
    {
        $baseUri = '';

        if (
            isset($_SERVER['REQUEST_URI'])
            && preg_match(
                '/^(.+)\/((api|widgets|modules|include|authentication)\/|main(\.get)?\.php).+/',
                $_SERVER['REQUEST_URI'],
                $matches
            )
        ) {
            $baseUri = $matches[1];
        }

        return $baseUri;
    }

    /**
     * Validate the data sent.
     *
     * @param Request $request Request sent by client
     * @param string $jsonValidationFile Json validation file
     *
     * @throws \InvalidArgumentException
     */
    protected function validateDataSent(Request $request, string $jsonValidationFile): void
    {
        // We want to enforce the decoding as possible objects.
        $receivedData = json_decode((string) $request->getContent(), false);
        if (! is_array($receivedData) && ! ($receivedData instanceof \stdClass)) {
            throw new \InvalidArgumentException('Error when decoding your sent data');
        }

        $validator = new Validator();
        $validator->validate(
            $receivedData,
            (object) [
                '$ref' => 'file://' . realpath(
                    $jsonValidationFile
                ),
            ],
            Constraint::CHECK_MODE_VALIDATE_SCHEMA
        );

        if (! $validator->isValid()) {
            $message = '';
            $this->error('Invalid request body');
            foreach ($validator->getErrors() as $error) {
                $message .= ! empty($error['property'])
                    ? sprintf("[%s] %s\n", $error['property'], $error['message'])
                    : sprintf("%s\n", $error['message']);
            }

            throw new \InvalidArgumentException($message);
        }
    }

    /**
     * Validate the data sent and retrieve it.
     *
     * @param Request $request Request sent by client
     * @param string $jsonValidationFile Json validation file
     *
     * @throws \InvalidArgumentException
     *
     * @return array<string, mixed>
     */
    protected function validateAndRetrieveDataSent(Request $request, string $jsonValidationFile): array
    {
        $this->validateDataSent($request, $jsonValidationFile);

        return json_decode((string) $request->getContent(), true);
    }
}
