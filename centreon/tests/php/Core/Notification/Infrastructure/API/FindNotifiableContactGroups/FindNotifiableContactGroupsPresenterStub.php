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
 * For more information : user@centreon.com
 *
 */

declare(strict_types=1);

namespace Tests\Core\Notification\Infrastructure\API\FindNotifiableContactGroups;

use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Notification\Application\UseCase\FindNotifiableContactGroups\{
    FindNotifiableContactGroupsPresenterInterface as PresenterInterface,
    FindNotifiableContactGroupsResponse
};

class FindNotifiableContactGroupsPresenterStub extends AbstractPresenter implements PresenterInterface
{
    /** @var FindNotifiableContactGroupsResponse|null */
    public ?FindNotifiableContactGroupsResponse $response = null;

    /** @var ResponseStatusInterface|null */
    public ?ResponseStatusInterface $responseStatus = null;

    /**
     * @param PresenterFormatterInterface $presenterFormatter
     */
    public function __construct(protected PresenterFormatterInterface $presenterFormatter)
    {
        parent::__construct($presenterFormatter);
    }

    /**
     * @inheritDoc
     */
    public function presentResponse(FindNotifiableContactGroupsResponse|ResponseStatusInterface $data): void
    {
        if ($data instanceof ResponseStatusInterface) {
            $this->responseStatus = $data;
        } else {
            $this->response = $data;
        }
    }
}
