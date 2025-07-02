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

namespace Centreon\Application\Controller\Configuration;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Exception\EntityNotFoundException;
use Centreon\Domain\Exception\TimeoutException;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\MonitoringServer\Exception\MonitoringServerException;
use Centreon\Domain\MonitoringServer\Interfaces\MonitoringServerServiceInterface;
use Centreon\Domain\MonitoringServer\MonitoringServer;
use Centreon\Domain\MonitoringServer\UseCase\GenerateAllConfigurations;
use Centreon\Domain\MonitoringServer\UseCase\GenerateConfiguration;
use Centreon\Domain\MonitoringServer\UseCase\ReloadAllConfigurations;
use Centreon\Domain\MonitoringServer\UseCase\ReloadConfiguration;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use FOS\RestBundle\Context\Context;
use FOS\RestBundle\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * This class is designed to manage all requests concerning monitoring servers
 *
 * @package Centreon\Application\Controller
 */
class MonitoringServerController extends AbstractController
{
    use LoggerTrait;

    /**
     * @param MonitoringServerServiceInterface $monitoringServerService
     * @param bool $isCloudPlatform
     */
    public function __construct(
        private readonly MonitoringServerServiceInterface $monitoringServerService,
        private readonly bool $isCloudPlatform
    ) {
    }

    /**
     * Entry point to find a monitoring server
     *
     * @param RequestParametersInterface $requestParameters
     *
     * @throws \Exception
     *
     * @return View
     */
    public function findServers(RequestParametersInterface $requestParameters): View
    {
        $this->denyAccessUnlessGrantedForApiConfiguration();
        $context = (new Context())->setGroups([
            MonitoringServer::SERIALIZER_GROUP_MAIN,
        ]);

        $servers = $this->monitoringServerService->findServers();

        /**
         * @var Contact $user
         */
        $user = $this->getUser();
        if ($this->isCloudPlatform && ! $user->isAdmin()) {
            $excludeCentral = $requestParameters->getExtraParameter('exclude_central');

            if (in_array($excludeCentral, [true, 'true', 1], true)) {
                $remoteServers = $this->monitoringServerService->findRemoteServersIps();
                $servers = array_values(array_filter($servers, function ($server) use ($remoteServers) {
                    return ! ($server->isLocalhost() && ! in_array($server->getAddress(), $remoteServers, true));
                }));

                $requestParameters->setTotal(count($servers));
            }
        }

        return $this->view(
            [
                'result' => $servers,
                'meta' => $requestParameters->toArray(),
            ]
        )->setContext($context);
    }

    /**
     * @param GenerateConfiguration $generateConfiguration
     * @param int $monitoringServerId
     * @throws EntityNotFoundException
     * @throws MonitoringServerException
     * @return View
     */
    public function generateConfiguration(GenerateConfiguration $generateConfiguration, int $monitoringServerId): View
    {
        $this->denyAccessUnlessGrantedForApiConfiguration();
        $this->execute(
            function () use ($generateConfiguration, $monitoringServerId): void {
                $generateConfiguration->execute($monitoringServerId);
            }
        );

        return $this->view(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param GenerateAllConfigurations $generateAllConfigurations
     * @throws EntityNotFoundException
     * @throws MonitoringServerException
     * @return View
     */
    public function generateAllConfigurations(GenerateAllConfigurations $generateAllConfigurations): View
    {
        $this->denyAccessUnlessGrantedForApiConfiguration();
        $this->execute(
            function () use ($generateAllConfigurations): void {
                $generateAllConfigurations->execute();
            }
        );

        return $this->view(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param ReloadConfiguration $reloadConfiguration
     * @param int $monitoringServerId
     * @throws EntityNotFoundException
     * @throws MonitoringServerException
     * @return View
     */
    public function reloadConfiguration(ReloadConfiguration $reloadConfiguration, int $monitoringServerId): View
    {
        $this->denyAccessUnlessGrantedForApiConfiguration();
        $this->execute(
            function () use ($reloadConfiguration, $monitoringServerId): void {
                $reloadConfiguration->execute($monitoringServerId);
            }
        );

        return $this->view(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param ReloadAllConfigurations $reloadAllConfigurations
     * @throws EntityNotFoundException
     * @throws MonitoringServerException
     * @return View
     */
    public function reloadAllConfigurations(ReloadAllConfigurations $reloadAllConfigurations): View
    {
        $this->denyAccessUnlessGrantedForApiConfiguration();
        $this->execute(
            function () use ($reloadAllConfigurations): void {
                $reloadAllConfigurations->execute();
            }
        );

        return $this->view(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Generate and reload the configuration of a monitoring server.
     *
     * @param GenerateConfiguration $generateConfiguration
     * @param ReloadConfiguration $reloadConfiguration
     * @param int $monitoringServerId
     * @throws EntityNotFoundException
     * @throws MonitoringServerException
     * @return View
     */
    public function generateAndReloadConfiguration(
        GenerateConfiguration $generateConfiguration,
        ReloadConfiguration $reloadConfiguration,
        int $monitoringServerId
    ): View {
        $this->denyAccessUnlessGrantedForApiConfiguration();
        $this->execute(
            function () use ($generateConfiguration, $reloadConfiguration, $monitoringServerId): void {
                $generateConfiguration->execute($monitoringServerId);
                $reloadConfiguration->execute($monitoringServerId);
            }
        );

        return $this->view(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Generate and reload all monitoring servers configurations.
     *
     * @param GenerateAllConfigurations $generateAllConfigurations
     * @param ReloadAllConfigurations $reloadAllConfigurations
     * @throws EntityNotFoundException
     * @throws MonitoringServerException
     * @return View
     */
    public function generateAndReloadAllConfigurations(
        GenerateAllConfigurations $generateAllConfigurations,
        ReloadAllConfigurations $reloadAllConfigurations
    ): View {
        $this->denyAccessUnlessGrantedForApiConfiguration();
        $this->execute(
            function () use ($generateAllConfigurations, $reloadAllConfigurations): void {
                $generateAllConfigurations->execute();
                $reloadAllConfigurations->execute();
            }
        );

        return $this->view(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param callable $callable
     * @throws EntityNotFoundException
     * @throws MonitoringServerException
     */
    private function execute(callable $callable): void
    {
        /**
         * @var Contact $user
         */
        $user = $this->getUser();
        try {
            if (! $user->isAdmin() && ! $user->hasRole(Contact::ROLE_GENERATE_CONFIGURATION)) {
                throw new AccessDeniedException('Insufficient rights (required: ROLE_GENERATE_CONFIGURATION)');
            }

            $callable();
        } catch (TimeoutException $ex) {
            $this->error($ex->getMessage());

            throw new MonitoringServerException(
                'The operation timed out - please use the legacy export menu to workaround this problem'
            );
        } catch (EntityNotFoundException|AccessDeniedException $ex) {
            $this->error($ex->getMessage());

            throw $ex;
        } catch (\Exception $ex) {
            $this->error($ex->getMessage());

            throw new MonitoringServerException(
                'There was an consistency error in the exported files  - please use the legacy export menu to '
                . 'troubleshoot'
            );
        }
    }
}
