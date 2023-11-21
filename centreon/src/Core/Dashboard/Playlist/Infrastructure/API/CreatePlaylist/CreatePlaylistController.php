<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Core\Dashboard\Playlist\Infrastructure\API\CreatePlaylist;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Dashboard\Playlist\Application\UseCase\CreatePlaylist\CreatePlaylist;
use Core\Dashboard\Playlist\Application\UseCase\CreatePlaylist\CreatePlaylistRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class CreatePlaylistController extends AbstractController
{
    use LoggerTrait;

    /**
     * @param Request $request
     * @param CreatePlaylist $useCase
     * @param CreatePlaylistPresenter $presenter
     *
     * @throws AccessDeniedException
     *
     * @return Response
     */
    public function __invoke(
        Request $request,
        CreatePlaylist $useCase,
        CreatePlaylistPresenter $presenter
    ): Response {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        try {
            $createPlaylistRequest = $this->createRequest($request);
            $useCase($presenter, $createPlaylistRequest);
        } catch (\InvalidArgumentException $ex) {
            $this->error($ex->getMessage(), ['trace' => (string) $ex]);
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));
        }

        return $presenter->show();
    }

    /**
     * Create Request DTO.
     *
     * @param Request $request
     *
     * @throws \InvalidArgumentException
     *
     * @return CreatePlaylistRequest
     */
    private function createRequest(Request $request): CreatePlaylistRequest
    {
        /**
         * @var array{
         *  name: string,
         *  description: string,
         *  dashboards: array<array{id:int,order:int}>,
         *  rotation_time: int,
         *  is_public: bool
         * } $requestData
         */
        $requestData = $this->validateAndRetrieveDataSent($request, __DIR__ . '/CreatePlaylistSchema.json');
        $createPlaylistRequest = new CreatePlaylistRequest();
        $createPlaylistRequest->name = $requestData['name'];
        $createPlaylistRequest->description = $requestData['description'];
        $createPlaylistRequest->dashboards = $requestData['dashboards'];
        $createPlaylistRequest->rotationTime = $requestData['rotation_time'];
        $createPlaylistRequest->isPublic = $requestData['is_public'];

        return $createPlaylistRequest;
    }
}
