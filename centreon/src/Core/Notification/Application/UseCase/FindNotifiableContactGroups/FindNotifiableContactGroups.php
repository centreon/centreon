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
 * For more information : contact@centreon.com
 *
 */

declare(strict_types=1);

namespace Core\Notification\Application\UseCase\FindNotifiableContactGroups;

use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Contact\Application\Repository\ReadContactGroupRepositoryInterface;
use Core\Contact\Domain\Model\ContactGroup;
use Core\Notification\Application\UseCase\FindNotifiableContactGroups\Response\NotifiableContactGroupDto;

final class FindNotifiableContactGroups
{
    use LoggerTrait;

    /**
     * @param ReadContactGroupRepositoryInterface $contactGroupRepository
     */
    public function __construct(private readonly ReadContactGroupRepositoryInterface $contactGroupRepository)
    {
    }

    /**
     * @param FindNotifiableContactGroupsPresenterInterface $presenter
     */
    public function __invoke(FindNotifiableContactGroupsPresenterInterface $presenter): void
    {
        try {
            $this->info('Retrieving all contact groups.');
            $contactGroups = $this->contactGroupRepository->findAll();
            if ([] === $contactGroups) {
                $this->error('Contact Groups not found');
                $response = new NotFoundResponse('Contact Groups');
            } else {
                $response = $this->createResponseDto($contactGroups);
            }
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $response = new ErrorResponse(_('Error while retrieving contact groups'));
        }

        $presenter->presentResponse($response);
    }

    /**
     * @param array<ContactGroup> $contactGroups
     *
     * @return FindNotifiableContactGroupsResponse
     */
    private function createResponseDto(array $contactGroups): FindNotifiableContactGroupsResponse
    {
        $responseDto = new FindNotifiableContactGroupsResponse();
        foreach ($contactGroups as $contactGroup) {
            $notifiableContactGroupDto = new NotifiableContactGroupDto();
            $notifiableContactGroupDto->id = $contactGroup->getId();
            $notifiableContactGroupDto->name = $contactGroup->getName();
            $responseDto->notifiableContactGroups[] = $notifiableContactGroupDto;
        }

        return $responseDto;
    }
}
