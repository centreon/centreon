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

namespace Core\Media\Infrastructure\API\FindImageFolders;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Common\Infrastructure\ExceptionLogger\ExceptionLogger;
use Core\Media\Application\UseCase\FindImageFolders\FindImageFolders;
use Core\Media\Domain\Model\ImageFolder\ImageFolder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class FindImageFoldersController extends AbstractController
{
    public function __construct(
        private readonly ExceptionLogger $exceptionLogger,
        private readonly RequestParametersInterface $requestParameters,
        private readonly SerializerInterface $serializer,
    ) {
    }

    #[Route(
        path: '/configuration/media/folders',
        name: 'FindImageFolders',
        methods: 'GET',
    )]
    public function __invoke(
        FindImageFolders $useCase,
    ): Response {
        try {
            $response = $useCase();

            $imageFolders = array_map(
                static fn (ImageFolder $folder): ImageFolderDto => new ImageFolderDto(
                    id: $folder->id()->value,
                    name: $folder->name()->value,
                    alias: $folder->alias()?->value,
                    comment: $folder->description()?->value,
                ),
                $response->imageFolders
            );

            return JsonResponse::fromJsonString(
                $this->serializer->serialize(
                    [
                        'result' => $imageFolders,
                        'meta' => $this->requestParameters->toArray(),
                    ],
                    JsonEncoder::FORMAT,
                )
            );
        } catch (RepositoryException|\Exception|ExceptionInterface $exception) {
            $this->exceptionLogger->log($exception);

            return new JsonResponse(
                data: [
                    'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                    'message' => $exception->getMessage(),
                ]
            );
        }
    }
}
