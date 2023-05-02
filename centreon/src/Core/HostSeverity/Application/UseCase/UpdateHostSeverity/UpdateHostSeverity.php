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

namespace Core\HostSeverity\Application\UseCase\UpdateHostSeverity;

use Assert\AssertionFailedException;
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
use Core\HostSeverity\Application\Exception\HostSeverityException;
use Core\HostSeverity\Application\Repository\ReadHostSeverityRepositoryInterface;
use Core\HostSeverity\Application\Repository\WriteHostSeverityRepositoryInterface;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\ViewImg\Application\Repository\ReadViewImgRepositoryInterface;

final class UpdateHostSeverity
{
    use LoggerTrait;

    public function __construct(
        private readonly WriteHostSeverityRepositoryInterface $writeHostSeverityRepository,
        private readonly ReadHostSeverityRepositoryInterface $readHostSeverityRepository,
        private readonly ReadViewImgRepositoryInterface $readViewImgRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ContactInterface $user
    ) {
    }

    /**
     * @param UpdateHostSeverityRequest $request
     * @param DefaultPresenter $presenter
     */
    public function __invoke(UpdateHostSeverityRequest $request, PresenterInterface $presenter): void
    {
        try {
            if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_CATEGORIES_READ_WRITE)) {
                $this->error(
                    "User doesn't have sufficient rights to update host severities",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(HostSeverityException::writeActionsNotAllowed()->getMessage())
                );

                return;
            }

            if ($this->user->isAdmin()) {
                if (! $this->readHostSeverityRepository->exists($request->id)) {
                    $this->error('Host severity not found', [
                        'hostseverity_id' => $request->id,
                    ]);
                    $presenter->setResponseStatus(new NotFoundResponse('Host severity'));

                    return;
                }
            } else {
                $accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
                if (! $this->readHostSeverityRepository->existsByAccessGroups($request->id, $accessGroups)) {
                    $this->error('Host severity not found', [
                        'hostseverity_id' => $request->id,
                        'accessgroups' => $accessGroups,
                    ]);
                    $presenter->setResponseStatus(new NotFoundResponse('Host severity'));

                    return;
                }
            }

            $hostSeverity = $this->readHostSeverityRepository->findById($request->id);
            if (! $hostSeverity) {
                $presenter->setResponseStatus(
                    new ErrorResponse(HostSeverityException::errorWhileRetrievingObject())
                );

                return;
            }

            if (
                $hostSeverity->getName() !== $request->name
                && $this->readHostSeverityRepository->existsByName(new TrimmedString($request->name))
            ) {
                $this->error(
                    'Host severity name already exists',
                    ['hostseverity_name' => $request->name]
                );
                $presenter->setResponseStatus(
                    new ConflictResponse(HostSeverityException::hostNameAlreadyExists())
                );

                return;
            }

            if (
                0 === $request->iconId
                || ! $this->readViewImgRepository->existsOne($request->iconId)
            ) {
                $this->error(
                    'Host severity icon does not exist',
                    ['hostseverity_name' => $request->name]
                );
                $presenter->setResponseStatus(
                    new ConflictResponse(HostSeverityException::iconDoesNotExist($request->iconId))
                );

                return;
            }

            $hostSeverity->setName($request->name);
            $hostSeverity->setAlias($request->alias);
            $hostSeverity->setIconId($request->iconId);
            $hostSeverity->setLevel($request->level);
            $hostSeverity->setActivated($request->isActivated);
            $hostSeverity->setComment($request->comment);

            $this->writeHostSeverityRepository->update($hostSeverity);

            $presenter->present(new NoContentResponse());
        } catch (AssertionFailedException $ex) {
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(
                new ErrorResponse(HostSeverityException::updateHostSeverity($ex))
            );
            $this->error($ex->getMessage());
        }
    }
}
