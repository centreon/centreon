<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Core\ServiceSeverity\Application\UseCase\GetServiceSeverity;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\ServiceSeverity\Application\Exception\ServiceSeverityException;
use Core\ServiceSeverity\Application\Repository\ReadServiceSeverityRepositoryInterface;
use Core\ServiceSeverity\Domain\Model\ServiceSeverity;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\ServiceSeverity\Infrastructure\API\GetServiceSeverity\GetServiceSeverityPresenter;

final class GetServiceSeverity
{
    use LoggerTrait;

     /** @var AccessGroup[] */
    private array $accessGroups;

    public function __construct(
        private readonly ReadServiceSeverityRepositoryInterface $readServiceSeverityRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ContactInterface $user,
    ) {
    }

    /**
     * @param GetServiceSeverityPresenter $presenter
     * @param int $serviceSeverityId
     */
    public function __invoke(PresenterInterface $presenter, int $serviceSeverityId): void
    {
        try {
            if (
                ! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_SERVICES_CATEGORIES_READ)
                && ! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_SERVICES_CATEGORIES_READ_WRITE)
            ) {
                $this->error(
                    "User doesn't have sufficient rights to see services severities",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(ServiceSeverityException::accessNotAllowed())
                );

                return;
            }

            $serviceSeverity = null;
            if ($this->user->isAdmin()) {
                $serviceSeverity = $this->readServiceSeverityRepository->findById($serviceSeverityId);

            } else {
                
                $this->accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
                if($this->readServiceSeverityRepository->existsByAccessGroups(
                    $serviceSeverityId,
                    $this->accessGroups
                )) {
                    $serviceSeverity = $this->readServiceSeverityRepository->findById($serviceSeverityId);
                }
            }

            if (! $serviceSeverity) {
                 $this->error(
                    'ServiceSeverity not found',
                    ['service_severity_id' => $serviceSeverityId]
                );
                $presenter->setResponseStatus(new NotFoundResponse('ServiceSeverity'));

                return;
            }
        
            $presenter->present($this->createResponse($serviceSeverity));
        } catch (RequestParametersTranslatorException $ex) {
            $presenter->setResponseStatus(new ErrorResponse($ex->getMessage()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(
                new ErrorResponse(ServiceSeverityException::errorWhileRetrieving())
            );
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @param ServiceSeverity $serviceSeverity
     *
     * @throws \Throwable
     *
     *
     * @return GetServiceSeverityResponse
     */
    private function createResponse(ServiceSeverity $serviceSeverity): GetServiceSeverityResponse
    {
        

        $response = new GetServiceSeverityResponse();
        $response->id = $serviceSeverity->getId();
        $response->name = $serviceSeverity->getName();
        $response->alias = $serviceSeverity->getAlias();
        $response->level = $serviceSeverity->getLevel();
        $response->iconId = $serviceSeverity->getIconId();
        $response->isActivated = $serviceSeverity->isActivated();
       

        return $response;
    }
}