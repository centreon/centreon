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

namespace Centreon\Domain\Monitoring\MonitoringResource;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Monitoring\MonitoringResource\Exception\MonitoringResourceException;
use Centreon\Domain\Monitoring\MonitoringResource\Interfaces\MonitoringResourceServiceInterface;
use Centreon\Domain\Monitoring\MonitoringResource\Interfaces\MonitoringResourceRepositoryInterface;
use Centreon\Domain\Monitoring\ResourceFilter;

/**
 * This class is designed to manage the monitoring resources.
 *
 * @package Centreon\Domain\Monitoring\MonitoringResource
 */
class MonitoringResourceService implements MonitoringResourceServiceInterface
{
    /**
     * @var MonitoringResourceRepositoryInterface
     */
    private $monitoringResourceRepository;

    /**
     * @var ContactInterface
     */
    private $contact;

    /**
     * @var ResourceFilter
     */
    private $filter;

    /**
     * @param MonitoringResourceRepositoryInterface $monitoringResourceRepository
     * @param ContactInterface $contact
     */
    public function __construct(
        MonitoringResourceRepositoryInterface $monitoringResourceRepository,
        ContactInterface $contact,
        ResourceFilter $filter
    ) {
        $this->contact = $contact;
        $this->monitoringResourceRepository = $monitoringResourceRepository;
        $this->filter = $filter;
    }

    /**
     * @inheritDoc
     */
    public function findAllWithAcl(): array
    {
        try {
            return $this->monitoringResourceRepository->findAllByContact($this->filter, $this->contact);
        } catch (\Throwable $ex) {
            throw MonitoringResourceException::findMonitoringResourcesException($ex);
        }
    }

    /**
     * @inheritDoc
     */
    public function findAllWithoutAcl(): array
    {
        try {
            return $this->monitoringResourceRepository->findAll($this->filter);
        } catch (\Throwable $ex) {
            throw MonitoringResourceException::findMonitoringResourcesException($ex);
        }
    }
}
