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

namespace Core\HostSeverity\Application\UseCase\AddHostSeverity;

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\CreatedResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\HostSeverity\Application\Exception\HostSeverityException;
use Core\HostSeverity\Application\Repository\ReadHostSeverityRepositoryInterface;
use Core\HostSeverity\Application\Repository\WriteHostSeverityRepositoryInterface;
use Core\HostSeverity\Domain\Model\HostSeverity;
use Core\HostSeverity\Domain\Model\NewHostSeverity;
use Core\HostSeverity\Infrastructure\API\AddHostSeverity\AddHostSeverityPresenter;
use Core\ViewImg\Application\Repository\ReadViewImgRepositoryInterface;

final class AddHostSeverity
{
    use LoggerTrait;

    public function __construct(
        private readonly WriteHostSeverityRepositoryInterface $writeHostSeverityRepository,
        private readonly ReadHostSeverityRepositoryInterface $readHostSeverityRepository,
        private readonly ReadViewImgRepositoryInterface $readViewImgRepository,
        private readonly ContactInterface $user
    ) {
    }

    /**
     * @param AddHostSeverityRequest $request
     * @param AddHostSeverityPresenter $presenter
     */
    public function __invoke(AddHostSeverityRequest $request, PresenterInterface $presenter): void
    {
        try {
            if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_CATEGORIES_READ_WRITE)) {
                $this->error(
                    "User doesn't have sufficient rights to add host severities",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(HostSeverityException::addNotAllowed()->getMessage())
                );
            } elseif ($this->readHostSeverityRepository->existsByName(trim($request->name))) {
                $this->error(
                    'Host severity name already exists',
                    ['hostseverity_name' => trim($request->name)]
                );
                $presenter->setResponseStatus(
                    new ConflictResponse(HostSeverityException::hostNameAlreadyExists())
                );
            } elseif (
                0 === $request->iconId
                || ! $this->readViewImgRepository->existsOne($request->iconId)
            ) {
                $this->error(
                    'Host severity icon does not exists',
                    ['hostseverity_name' => trim($request->name)]
                );
                $presenter->setResponseStatus(
                    new ConflictResponse(HostSeverityException::iconDoesNotExist($request->iconId))
                );
            } else {
                $newHostSeverity = new NewHostSeverity(
                    trim($request->name),
                    trim($request->alias),
                    $request->level,
                    $request->iconId,
                );
                $newHostSeverity->setActivated($request->isActivated);
                $newHostSeverity->setComment($request->comment ? trim($request->comment) : null);

                $hostSeverityId = $this->writeHostSeverityRepository->add($newHostSeverity);
                $hostSeverity = $this->readHostSeverityRepository->findById($hostSeverityId);
                if (! $hostSeverity) {
                    $presenter->setResponseStatus(
                        new ErrorResponse(HostSeverityException::errorWhileRetrievingJustCreated())
                    );

                    return;
                }

                $presenter->present(
                    new CreatedResponse($hostSeverityId, $this->createResponse($hostSeverity))
                );
            }
        } catch (AssertionFailedException $ex) {
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(
                new ErrorResponse(HostSeverityException::addHostSeverity($ex))
            );
            $this->error($ex->getMessage());
        }
    }

    /**
     * @param HostSeverity|null $hostSeverity
     *
     * @return AddHostSeverityResponse
     */
    private function createResponse(?HostSeverity $hostSeverity): AddHostSeverityResponse
    {
        $response = new AddHostSeverityResponse();
        if ($hostSeverity !== null) {
            $response->id = $hostSeverity->getId();
            $response->name = $hostSeverity->getName();
            $response->alias = $hostSeverity->getAlias();
            $response->level = $hostSeverity->getLevel();
            $response->iconId = $hostSeverity->getIconId();
            $response->isActivated = $hostSeverity->isActivated();
            $response->comment = $hostSeverity->getComment();
        }

        return $response;
    }
}
