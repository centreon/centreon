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

namespace Core\Dashboard\Playlist\Infrastructure\API\SharePlaylist;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Dashboard\Playlist\Application\UseCase\SharePlaylist\SharePlaylist;
use Core\Dashboard\Playlist\Application\UseCase\SharePlaylist\SharePlaylistRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class SharePlaylistController extends AbstractController
{
    use LoggerTrait;

    /**
     * @param integer $playlistId
     * @param Request $request
     * @param SharePlaylist $useCase
     * @param SharePlaylistPresenter $presenter
     *
     * @return Response
     */
    public function __invoke(
        int $playlistId,
        Request $request,
        SharePlaylist $useCase,
        SharePlaylistPresenter $presenter
    ): Response {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        try {
            $sharePlaylistRequest = $this->createSharePlaylistRequest($request);
            $useCase($playlistId, $sharePlaylistRequest, $presenter);
        } catch(\InvalidArgumentException $ex) {
            $this->error($ex->getMessage(), ['trace' => (string) $ex]);
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));
        }

        return $presenter->show();
    }

    /**
     * Create Request Dto.
     *
     * @param Request $request
     *
     * @throws \InvalidArgumentException
     *
     * @return SharePlaylistRequest
     */
    private function createSharePlaylistRequest(Request $request): SharePlaylistRequest
    {
        /**
         * @var array{
         *  contacts: array{}|array<array{id:int, role: string}>,
         *  contactgroups: array{}|array<array{id:int, role: string}>,
         * } $requestData
         */
        $requestData = $this->validateAndRetrieveDataSent($request, __DIR__ . '/SharePlaylistSchema.json');
        $sharePlaylistRequest = new SharePlaylistRequest();
        $sharePlaylistRequest->contacts= $requestData['contacts'];
        $sharePlaylistRequest->contactGroups = $requestData['contactgroups'];

        return $sharePlaylistRequest;
    }
}