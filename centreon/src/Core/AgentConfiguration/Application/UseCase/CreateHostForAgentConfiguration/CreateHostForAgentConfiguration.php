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

namespace Core\AgentConfiguration\Application\UseCase\CreateHostForAgentConfiguration;

use CentreonLog;
use Core\AgentConfiguration\Application\Repository\ReadAgentConfigurationRepositoryInterface;
use Core\Common\Domain\TrimmedString;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\Host\Application\Repository\WriteHostRepositoryInterface;
use Core\Host\Domain\Model\NewHost;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\Service\Application\Repository\WriteServiceRepositoryInterface;
use Core\Service\Domain\Model\NewService;
use Core\ServiceTemplate\Application\Repository\ReadServiceTemplateRepositoryInterface;
use Webmozart\Assert\Assert;

final class CreateHostForAgentConfiguration
{
    public function __construct(
        private readonly ReadAgentConfigurationRepositoryInterface $agentConfigurationRepository,
        private readonly ReadHostRepositoryInterface $readHostRepository,
        private readonly ReadHostTemplateRepositoryInterface $readHostTemplateRepository,
        private readonly WriteHostRepositoryInterface $writeHostRepository,
        private readonly ReadServiceTemplateRepositoryInterface $readServiceTemplateRepository,
        private readonly WriteServiceRepositoryInterface $writeServiceRepository,
    ) {
    }

    /**
     * @throws \Throwable
     *
     * @return array{success: bool, message: string, details: string}
     */
    public function __invoke(CreateHostForAgentConfigurationRequest $request): array
    {
        $pollerId = $request->pollerId;
        $hostName = $request->hostName;
        $address = $request->address;
        $templateName = $request->templateName;
        $agentConfiguration = null;

        CentreonLog::create()->debug(
            logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
            message: '[AC][create_host_auto] Checking arguments.',
            customContext: [
                'poller_id' => $pollerId,
            ]
        );

        Assert::positiveInteger($pollerId, 'The poller id must be a positive integer.');
        $agentConfiguration = $this->agentConfigurationRepository->findByPollerId($pollerId);
        if ($agentConfiguration === null) {
            CentreonLog::create()->error(
                logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
                message: '[AC][create_host_auto] Poller not linked to any agent configuration.',
                customContext: [
                    'poller_id' => $pollerId,
                ]
            );

            return [
                'success' => false,
                'message' => 'Host creation process failed.',
                'details' => sprintf('Reason: Agent configuration not found for poller %d.', $pollerId),
            ];
        }

        Assert::notEmpty($hostName, 'The host name must not be empty.');
        if ($this->readHostRepository->existsByName($hostName)) {
            CentreonLog::create()->error(
                logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
                message: '[AC][create_host_auto] Host name already exists.',
                customContext: [
                    'poller_id' => $pollerId,
                    'agent_configuration_id' => $agentConfiguration->getId(),
                    'host_name' => $hostName,
                ]
            );

            return [
                'success' => false,
                'message' => 'Host creation process failed.',
                'details' => sprintf('Reason: A host with the name "%s" already exists.', $hostName),
            ];
        }

        Assert::notEmpty($address, 'The address must not be empty.');

        CentreonLog::create()->debug(
            logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
            message: '[AC][create_host_auto] Checking agent configuration.',
            customContext: [
                'poller_id' => $pollerId,
                'agent_configuration_id' => $agentConfiguration->getId(),
            ]
        );

        $configData = $agentConfiguration->getConfiguration()->getData();
        $createHostAuto = $configData['create_host_auto'] ?? false;
        if ($createHostAuto === false) {
            CentreonLog::create()->info(
                logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
                message: '[AC][create_host_auto] Agent configuration is not set to create host automatically. Nothing to do.',
                customContext: [
                    'poller_id' => $pollerId,
                    'agent_configuration_id' => $agentConfiguration->getId(),
                ]
            );

            return [
                'success' => true,
                'message' => 'Host creation process skipped.',
                'details' => 'Reason: Agent configuration is not set to create host automatically.',
            ];
        }

        CentreonLog::create()->debug(
            logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
            message: '[AC][create_host_auto] Creating host.',
            customContext: [
                'poller_id' => $pollerId,
                'agent_configuration_id' => $agentConfiguration->getId(),
            ]
        );

        $hostId = $this->deployHost(
            $agentConfiguration->getId(),
            $hostName,
            $address,
            $templateName,
            $pollerId,
        );

        CentreonLog::create()->debug(
            logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
            message: '[AC][create_host_auto] Creating services.',
            customContext: [
                'poller_id' => $pollerId,
                'agent_configuration_id' => $agentConfiguration->getId(),
                'host_id' => $hostId,
            ]
        );

        $serviceIds = $this->deployServices($agentConfiguration->getId(), $hostId);

        CentreonLog::create()->debug(
            logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
            message: '[AC][create_host_auto] Host creation process completed successfully.',
            customContext: [
                'poller_id' => $pollerId,
                'agent_configuration_id' => $agentConfiguration->getId(),
                'host_id' => $hostId,
                'service_ids' => $serviceIds,
            ]
        );

        return [
            'success' => true,
            'message' => 'Host creation process completed successfully.',
            'details' => sprintf(
                'Host ID: %d, Service IDs: [%s]',
                $hostId,
                implode(', ', $serviceIds)
            ),
        ];
    }

    /**
     * @throws \Throwable
     */
    private function deployHost(
        int $agentConfigurationId,
        string $hostName,
        string $address,
        ?string $templateName,
        int $pollerId,
    ): int {
        $newHost = new NewHost(
            monitoringServerId: $pollerId,
            name: $hostName,
            address: $address,
            alias: $hostName,
        );
        $hostId = $this->writeHostRepository->add($newHost);

        $template = $templateName !== null ? $this->readHostTemplateRepository->findByName($templateName) : null;
        if ($template === null) {
            CentreonLog::create()->warning(
                logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
                message: '[AC][create_host_auto] Host template not found or undefined.',
                customContext: [
                    'agent_configuration_id' => $agentConfigurationId,
                    'host_template_name' => $templateName,
                ]
            );
        } else {
            $this->writeHostRepository->addParent(
                childId: $hostId,
                parentId: $template->getId(),
                order: 1
            );
        }

        CentreonLog::create()->debug(
            logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
            message: '[AC][create_host_auto] Host created successfully.',
            customContext: [
                'agent_configuration_id' => $agentConfigurationId,
                'host_id' => $hostId,
                'host_name' => $hostName,
                'template_id' => $template?->getId(),
                'address' => $address,
                'poller_id' => $pollerId,
            ]
        );

        return $hostId;
    }

    /**
     * @throws \Throwable
     *
     * @return int[]
     */
    private function deployServices(
        int $agentConfigurationId,
        int $hostId,
    ): array {
        $hostParents = $this->readHostRepository->findParents($hostId);
        if ($hostParents === []) {
            CentreonLog::create()->debug(
                logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
                message: '[AC][create_host_auto] No host templates found. No services to deploy.',
                customContext: [
                    'agent_configuration_id' => $agentConfigurationId,
                    'host_id' => $hostId,
                    'host_parents' => $hostParents,
                ]
            );

            return [];
        }

        $deployedServices = [];
        foreach ($hostParents as $hostParent) {
            $serviceTemplates = $this->readServiceTemplateRepository->findByHostId($hostParent['parent_id']);

            foreach ($serviceTemplates as $serviceTemplate) {
                $alias = $serviceTemplate->getAlias();
                if (array_key_exists($alias, $deployedServices, true)) {
                    CentreonLog::create()->debug(
                        logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
                        message: '[AC][create_host_auto] Service already exists with that name, skipping creation.',
                        customContext: [
                            'agent_configuration_id' => $agentConfigurationId,
                            'host_id' => $hostId,
                            'service_name' => $alias,
                        ]
                    );

                    continue;
                }
                $service = new NewService(
                    $alias,
                    $hostId,
                    null // command line must be inherited from template when you deploy services from a host
                );
                $service->setServiceTemplateParentId($serviceTemplate->getId());
                $service->setActivated(true);
                $serviceId = $this->writeServiceRepository->add($service);
                $service = $this->readServiceRepository->findById($serviceId);
                if ($service !== null) {
                    $deployedServices[$alias] = $service;

                    CentreonLog::create()->debug(
                        logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
                        message: '[AC][create_host_auto] Service created successfully.',
                        customContext: [
                            'agent_configuration_id' => $agentConfigurationId,
                            'host_id' => $hostId,
                            'service_id' => $service->getId(),
                            'service_name' => $service->getName(),
                        ]
                    );
                }
            }
        }

        if ($deployedServices === []) {
            CentreonLog::create()->debug(
                logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
                message: '[AC][create_host_auto] No service to deploy for the new host.',
                customContext: [
                    'agent_configuration_id' => $agentConfigurationId,
                    'host_id' => $hostId,
                ]
            );
        }

        return array_map(
            fn ($service) => $service->getId(),
            $deployedServices
        );
    }
}
