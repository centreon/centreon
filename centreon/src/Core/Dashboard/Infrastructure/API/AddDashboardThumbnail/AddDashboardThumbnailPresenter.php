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

namespace Core\Dashboard\Infrastructure\API\AddDashboardThumbnail;

use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Dashboard\Application\UseCase\AddDashboardThumbnail\AddDashboardThumbnailPresenterInterface;

final class AddDashboardThumbnailPresenter extends AbstractPresenter implements AddDashboardThumbnailPresenterInterface
{
    public function presentResponse(NoContentResponse|ResponseStatusInterface $data): void
    {
        if ($data instanceof NoContentResponse) {
            $this->present($data);
        } else {
            $this->setResponseStatus($data);
        }
    }
}
