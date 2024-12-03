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

namespace Core\HostCategory\Application\UseCase\UpdateHostCategory;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\Common\Domain\TrimmedString;
use Core\HostCategory\Application\Exception\HostCategoryException;
use Core\HostCategory\Application\Repository\ReadHostCategoryRepositoryInterface;
use Core\HostCategory\Application\Repository\WriteHostCategoryRepositoryInterface;
use Core\HostCategory\Domain\Model\HostCategory;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

final class UpdateHostCategory
{
    use LoggerTrait;

    public function __construct(
        private readonly WriteHostCategoryRepositoryInterface $writeHostCategoryRepository,
        private readonly ReadHostCategoryRepositoryInterface $readHostCategoryRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepositoryInterface,
        private readonly ContactInterface $user
    ) {
    }

    /**
     * @param UpdateHostCategoryRequest $request
     * @param PresenterInterface $presenter
     */
    public function __invoke(UpdateHostCategoryRequest $request, PresenterInterface $presenter): void
    {
        try {
            if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_CATEGORIES_READ_WRITE)) {
                $this->error('User doesn\'t have sufficient rights to edit host categories', [
                    'user_id' => $this->user->getId(),
                ]);
                $presenter->setResponseStatus(
                    new ForbiddenResponse(HostCategoryException::writingActionsNotAllowed())
                );

                return;
            }

            if ($this->user->isAdmin()) {
                if (! $this->readHostCategoryRepository->exists($request->id)) {
                    $this->error('Host category not found', [
                        'hostcategory_id' => $request->id,
                    ]);
                    $presenter->setResponseStatus(new NotFoundResponse('Host category'));

                    return;
                }
            } else {
                $accessGroups = $this->readAccessGroupRepositoryInterface->findByContact($this->user);
                if (! $this->readHostCategoryRepository->existsByAccessGroups($request->id, $accessGroups)) {
                    $this->error('Host category not found', [
                        'hostcategory_id' => $request->id,
                        'accessgroups' => $accessGroups,
                    ]);
                    $presenter->setResponseStatus(new NotFoundResponse('Host category'));

                    return;
                }
            }

            $hostCategory = $this->readHostCategoryRepository->findById($request->id);
            if (! $hostCategory) {
                $presenter->setResponseStatus(
                    new ErrorResponse(HostCategoryException::errorWhileRetrievingObject())
                );

                return;
            }

            if (
                $hostCategory->getName() !== $request->name
                && $this->readHostCategoryRepository->existsByName(new TrimmedString($request->name))
            ) {
                $this->error('Host category name already exists', [
                    'hostcategory_name' => trim($request->name),
                ]);
                $presenter->setResponseStatus(
                    new ConflictResponse(HostCategoryException::hostNameAlreadyExists())
                );

                return;
            }

            if ($this->hasDifferences($request, $hostCategory)) {
                $hostCategory->setName($request->name);
                $hostCategory->setAlias($request->alias);
                $hostCategory->setActivated($request->isActivated);
                $hostCategory->setComment($request->comment);

                $this->writeHostCategoryRepository->update($hostCategory);
            }

            $presenter->setResponseStatus(new NoContentResponse());

        } catch (\Assert\AssertionFailedException $ex) {
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(
                new ErrorResponse(HostCategoryException::updateHostCategory($ex))
            );
            $this->error($ex->getMessage());
        }
    }

    /**
     * Verify if the Request Payload has changed compare to retrieved hostCategory.
     *
     * @param UpdateHostCategoryRequest $request
     * @param HostCategory $hostCategory
     *
     * @return bool
     */
    private function hasDifferences(UpdateHostCategoryRequest $request, HostCategory $hostCategory): bool
    {
        return $request->name !== $hostCategory->getName()
            || $request->alias !== $hostCategory->getAlias()
            || $request->isActivated !== $hostCategory->isActivated()
            || $request->comment !== $hostCategory->getComment();
    }
}
