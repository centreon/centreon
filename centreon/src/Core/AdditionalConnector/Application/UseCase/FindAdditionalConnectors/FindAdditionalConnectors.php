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

namespace Core\AdditionalConnector\Application\UseCase\FindAdditionalConnectors;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use Core\AdditionalConnector\Application\Exception\AdditionalConnectorException;
use Core\AdditionalConnector\Application\Repository\ReadAdditionalConnectorRepositoryInterface;
use Core\AdditionalConnector\Domain\Model\AdditionalConnector;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Contact\Application\Repository\ReadContactRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

final class FindAdditionalConnectors
{
    use LoggerTrait;

    public function __construct(
        private readonly RequestParametersInterface $requestParameters,
        private readonly ReadAdditionalConnectorRepositoryInterface $readAdditionalConnectorRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ReadContactRepositoryInterface $readContactRepository,
        private readonly ContactInterface $user,
    ) {
    }

    public function __invoke(
        FindAdditionalConnectorsPresenterInterface $presenter
    ): void {
        try {
            if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_ADDITIONAL_CONNECTOR_RW)) {
                $this->error(
                    "User doesn't have sufficient rights to read additional connectors",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->presentResponse(
                    new ForbiddenResponse(AdditionalConnectorException::readNotAllowed())
                );

                return;
            }

            if ($this->user->isAdmin()) {
                $additionalConnectors = $this->readAdditionalConnectorRepository->findByRequestParameters($this->requestParameters);
            } else {
                $accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
                $additionalConnectors = $this->readAdditionalConnectorRepository->findByRequestParametersAndAccessGroups($this->requestParameters, $accessGroups);
            }

            $presenter->presentResponse($this->createResponse($additionalConnectors));
        } catch (RequestParametersTranslatorException $ex) {
            $presenter->presentResponse(new ErrorResponse($ex->getMessage()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->presentResponse(new ErrorResponse(
                $ex instanceof AdditionalConnectorException
                    ? $ex
                    : AdditionalConnectorException::findAdditionalConnectors()
            ));
        }
    }

    /**
     * @param AdditionalConnector[] $additionalConnectors
     *
     * @return FindAdditionalConnectorsResponse
     */
    private function createResponse(array $additionalConnectors): FindAdditionalConnectorsResponse
    {
        $accDtos = [];
        foreach ($additionalConnectors as $acc) {
            $userIds = [];
            if ($acc->getCreatedBy() !== null) {
                $userIds[] = $acc->getCreatedBy();
            }
            if ($acc->getUpdatedBy() !== null) {
                $userIds[] = $acc->getUpdatedBy();
            }

            $users = $this->readContactRepository->findNamesByIds(...$userIds);

            $accDtos[] = new AdditionalConnectorDto(
                id: $acc->getId(),
                type: $acc->getType(),
                name: $acc->getName(),
                description: $acc->getDescription(),
                createdBy: $acc->getCreatedBy() ? $users[$acc->getCreatedBy()] : null,
                updatedBy: $acc->getUpdatedBy() ? $users[$acc->getUpdatedBy()] : null,
                createdAt: $acc->getCreatedAt(),
                updatedAt: $acc->getCreatedAt()
            );
        }

        return new FindAdditionalConnectorsResponse($accDtos);
    }
}
