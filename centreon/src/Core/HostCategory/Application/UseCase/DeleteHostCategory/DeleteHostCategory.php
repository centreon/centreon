<?php

/*
* Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
*
* Licensed under the Apache License, Version 2.0 (the "License");
* you may not use this file except in compliance with the License.
* You may obtain a copy of the License at
*
* http://www.apache.org/licenses/LICENSE-2.0
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

namespace Core\HostCategory\Application\UseCase\DeleteHostCategory;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\HostCategory\Application\Repository\WriteHostCategoryRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

class DeleteHostCategory
{
    use LoggerTrait;

    public function __construct(
        private WriteHostCategoryRepositoryInterface $writeHostCategoryRepository,
        private ReadAccessGroupRepositoryInterface $readAccessGroupRepositoryInterface,
        private ContactInterface $user
    ) {
    }

    public function __invoke(int $hostCategoryId, PresenterInterface $presenter): void
    {
        try {
            if ($this->user->isAdmin()) {
                $this->writeHostCategoryRepository->deleteById($hostCategoryId);
            } else {
                $this->debug('User is not admin, delete data via ACLs', ['user' => $this->user->getName()]);

                $accessGroups = $this->readAccessGroupRepositoryInterface->findByContact($this->user);
                $this->writeHostCategoryRepository->deleteByIdAndAccessGroups($hostCategoryId, $accessGroups);
            }

            $presenter->setResponseStatus(new NoContentResponse());
        } catch (\Throwable $th) {
            $presenter->setResponseStatus(new ErrorResponse('Error while deleting host category #' . $hostCategoryId));
            // TODO : translate error message
            $this->error($th->getMessage());
        }
    }
}
