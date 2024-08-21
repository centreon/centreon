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

namespace Core\Notification\Infrastructure\API\FindNotifiableContactGroups;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Notification\Application\UseCase\FindNotifiableContactGroups\{
    FindNotifiableContactGroupsPresenterInterface as PresenterInterface,
    FindNotifiableContactGroupsResponse
};

class FindNotifiableContactGroupsPresenter extends AbstractPresenter implements PresenterInterface
{
    /**
     * @param RequestParametersInterface $requestParameters
     * @param PresenterFormatterInterface $presenterFormatter
     */
    public function __construct(
        private readonly RequestParametersInterface $requestParameters,
        protected PresenterFormatterInterface $presenterFormatter
    ) {
        parent::__construct($presenterFormatter);
    }

    /**
     * @inheritDoc
     */
    public function presentResponse(FindNotifiableContactGroupsResponse|ResponseStatusInterface $data): void
    {
        if ($data instanceof ResponseStatusInterface) {
            $this->setResponseStatus($data);
        } else {
            $this->present(
                [
                    'result' => \array_map(static fn($notifiableContactGroupDto) => [
                        'id' => $notifiableContactGroupDto->id,
                        'name' => $notifiableContactGroupDto->name,
                    ], $data->notifiableContactGroups),
                    'meta' => $this->requestParameters->toArray(),
                ]
            );
        }
    }
}
