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

namespace Core\Dashboard\Playlist\Infrastructure\API\ListPlaylistShares;

use Centreon\Application\Controller\AbstractController;
use Core\Dashboard\Playlist\Application\UseCase\ListPlaylistShares\ListPlaylistShares;
use Symfony\Component\HttpFoundation\Response;

final class ListPlaylistSharesController extends AbstractController
{
    public function __invoke(int $playlistId, ListPlaylistShares $useCase, ListPlaylistSharesPresenter $presenter): Response
    {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        $useCase($playlistId, $presenter);

        return $presenter->show();
    }
}