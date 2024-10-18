<?php

/*
 * Copyright 2005 - 2021 Centreon (https://www.centreon.com/)
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

namespace Centreon\Domain\MonitoringServer\UseCase\RealTimeMonitoringServer;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\MonitoringServer\Exception\RealTimeMonitoringServerException;
use Centreon\Infrastructure\MonitoringServer\Repository\RealTimeMonitoringServerRepositoryRDB;
use Centreon\Domain\Log\LoggerTrait;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * This class is designed to represent a use case to find all monitoring servers.
 *
 * @package Centreon\Domain\MonitoringServer\UseCase\RealTimeMonitoringServer
 */
class FindRealTimeMonitoringServers
{
    use LoggerTrait;

    /**
     * FindRealTimeMonitoringServers constructor.
     *
     * @param RealTimeMonitoringServerRepositoryRDB $realTimeMonitoringServerRepository
     * @param ContactInterface $contact
     */
    public function __construct(
        readonly private RealTimeMonitoringServerRepositoryRDB $realTimeMonitoringServerRepository,
        readonly private ContactInterface $contact
    ) {
    }

    /**
     * Execute the use case for which this class was designed.
     *
     * @throws RealTimeMonitoringServerException
     * @throws \Throwable
     * @return FindRealTimeMonitoringServersResponse
     */
    public function execute(): FindRealTimeMonitoringServersResponse
    {
        $this->info('Find all realtime monitoring servers information.');

        if (! $this->contact->hasTopologyRole(Contact::ROLE_MONITORING_RESOURCES_STATUS_RW)) {
            $this->error('User doesn\'t have sufficient rights to see realtime monitoring servers', [
                    'user_id' => $this->contact->getId(),
                ]);
            throw new AccessDeniedException();
        }
        $response = new FindRealTimeMonitoringServersResponse();

        $realTimeMonitoringServers = [];

        if ($this->contact->isAdmin()) {
            try {
                $realTimeMonitoringServers = $this->realTimeMonitoringServerRepository->findAll();
            } catch (\Throwable $ex) {
                throw RealTimeMonitoringServerException::findRealTimeMonitoringServersException($ex);
            }
        } else {
            $allowedMonitoringServers = $this->realTimeMonitoringServerRepository
                ->findAllowedMonitoringServers($this->contact);
            if ($allowedMonitoringServers !== []) {
                $allowedMonitoringServerIds = array_map(
                    function ($allowedMonitoringServer) {
                        return $allowedMonitoringServer->getId();
                    },
                    $allowedMonitoringServers
                );
                $this->info(
                    'Find realtime monitoring servers information for following ids: '
                    . implode(',', $allowedMonitoringServerIds)
                );
                try {
                    $realTimeMonitoringServers = $this->realTimeMonitoringServerRepository
                        ->findByIds($allowedMonitoringServerIds);
                } catch (\Throwable $ex) {
                    throw RealTimeMonitoringServerException::findRealTimeMonitoringServersException($ex);
                }
            } else {
                $this->info(
                    'Cannot find realtime monitoring servers information because user does not have access to anyone.'
                );
            }
        }

        $response->setRealTimeMonitoringServers($realTimeMonitoringServers);

        return $response;
    }
}
