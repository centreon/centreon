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

namespace Core\Dashboard\Infrastructure\API\FindDashboardContacts;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Dashboard\Application\UseCase\FindDashboardContacts\FindDashboardContactsPresenterInterface;
use Core\Dashboard\Application\UseCase\FindDashboardContacts\FindDashboardContactsResponse;
use Core\Dashboard\Application\UseCase\FindDashboardContacts\Response\ContactsResponseDto;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;

final class FindDashboardContactsPresenter extends AbstractPresenter implements FindDashboardContactsPresenterInterface
{
    public function __construct(
        protected RequestParametersInterface $requestParameters,
        PresenterFormatterInterface $presenterFormatter,
    )
    {
        parent::__construct($presenterFormatter);
    }

    public function presentResponse(FindDashboardContactsResponse|ResponseStatusInterface $data): void
    {
        if ($data instanceof FindDashboardContactsResponse) {
            $this->present([
                'result' => array_map(
                    $this->contactsResponseDtoToArray(...),
                    $data->contacts
                ),
                'meta' => $this->requestParameters->toArray(),
            ]);
        } else {
            $this->setResponseStatus($data);
        }
    }

    /**
     * @param ContactsResponseDto $dto
     *
     * @return array<mixed>
     */
    private function contactsResponseDtoToArray(ContactsResponseDto $dto): array
    {
        return [
            'id' => $dto->id,
            'name' => $dto->name,
        ];
    }
}
