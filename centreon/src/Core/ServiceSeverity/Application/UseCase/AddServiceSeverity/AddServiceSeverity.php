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

namespace Core\ServiceSeverity\Application\UseCase\AddServiceSeverity;

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
use Core\Common\Domain\TrimmedString;
use Core\ServiceSeverity\Application\Exception\ServiceSeverityException;
use Core\ServiceSeverity\Application\Repository\ReadServiceSeverityRepositoryInterface;
use Core\ServiceSeverity\Application\Repository\WriteServiceSeverityRepositoryInterface;
use Core\ServiceSeverity\Domain\Model\ServiceSeverity;
use Core\ServiceSeverity\Domain\Model\NewServiceSeverity;
use Core\ServiceSeverity\Infrastructure\API\AddServiceSeverity\AddServiceSeverityPresenter;
use Core\ViewImg\Application\Repository\ReadViewImgRepositoryInterface;

final class AddServiceSeverity
{
    use LoggerTrait;

    public function __construct(
        private readonly WriteServiceSeverityRepositoryInterface $writeServiceSeverityRepository,
        private readonly ReadServiceSeverityRepositoryInterface $readServiceSeverityRepository,
        private readonly ReadViewImgRepositoryInterface $readViewImgRepository,
        private readonly ContactInterface $user
    ) {
    }

    /**
     * @param AddServiceSeverityRequest $request
     * @param AddServiceSeverityPresenter $presenter
     */
    public function __invoke(AddServiceSeverityRequest $request, PresenterInterface $presenter): void
    {
        try {
            if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_SERVICES_CATEGORIES_READ_WRITE)) {
                $this->error(
                    "User doesn't have sufficient rights to add service severities",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(ServiceSeverityException::addNotAllowed()->getMessage())
                );
            } elseif ($this->readServiceSeverityRepository->existsByName(new TrimmedString($request->name))) {
                $this->error(
                    'Service severity name already exists',
                    ['serviceseverity_name' => $request->name]
                );
                $presenter->setResponseStatus(
                    new ConflictResponse(ServiceSeverityException::serviceNameAlreadyExists())
                );
            } elseif (
                0 === $request->iconId
                || ! $this->readViewImgRepository->existsOne($request->iconId)
            ) {
                $this->error(
                    'Service severity icon does not exist',
                    ['serviceseverity_name' => $request->name]
                );
                $presenter->setResponseStatus(
                    new ConflictResponse(ServiceSeverityException::iconDoesNotExist($request->iconId))
                );
            } else {
                $newServiceSeverity = new NewServiceSeverity(
                    $request->name,
                    $request->alias,
                    $request->level,
                    $request->iconId,
                );
                $newServiceSeverity->setActivated($request->isActivated);

                $serviceSeverityId = $this->writeServiceSeverityRepository->add($newServiceSeverity);
                $serviceSeverity = $this->readServiceSeverityRepository->findById($serviceSeverityId);
                $this->info('Add a new service severity', ['serviceseverity_id' => $serviceSeverityId]);
                if (! $serviceSeverity) {
                    $presenter->setResponseStatus(
                        new ErrorResponse(ServiceSeverityException::errorWhileRetrievingJustCreated())
                    );

                    return;
                }

                $presenter->present(
                    new CreatedResponse($serviceSeverityId, $this->createResponse($serviceSeverity))
                );
            }
        } catch (AssertionFailedException $ex) {
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(
                new ErrorResponse(ServiceSeverityException::addServiceSeverity($ex))
            );
            $this->error((string) $ex);
        }
    }

    /**
     * @param ServiceSeverity|null $serviceSeverity
     *
     * @return AddServiceSeverityResponse
     */
    private function createResponse(?ServiceSeverity $serviceSeverity): AddServiceSeverityResponse
    {
        $response = new AddServiceSeverityResponse();
        if ($serviceSeverity !== null) {
            $response->id = $serviceSeverity->getId();
            $response->name = $serviceSeverity->getName();
            $response->alias = $serviceSeverity->getAlias();
            $response->level = $serviceSeverity->getLevel();
            $response->iconId = $serviceSeverity->getIconId();
            $response->isActivated = $serviceSeverity->isActivated();
        }

        return $response;
    }
}
