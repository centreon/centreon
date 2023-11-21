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

declare(strict_types = 1);

namespace Tests\Core\Media\Infrastructure\API\AddMedia;

use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Media\Application\UseCase\AddMedia\AddMediaPresenterInterface;
use Core\Media\Application\UseCase\AddMedia\AddMediaResponse;

class AddMediaPresenterStub extends AbstractPresenter implements AddMediaPresenterInterface
{
    public AddMediaResponse|ResponseStatusInterface $response;

    public function presentResponse(AddMediaResponse|ResponseStatusInterface $response): void
    {
        $this->response = $response;
    }
}
