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

namespace Tests\CentreonRemote;

use CentreonACL;
use CentreonRestHttp;
use Pimple\Container;
use Pimple\Psr11\ServiceLocator;
use Centreon\Test\Mock\CentreonDB;
use CentreonRemote\ServiceProvider;
use CentreonRemote\Domain\Service\TaskService;
use CentreonRemote\Domain\Service\InformationsService;
use CentreonRemote\Domain\Service\NotifyMasterService;
use Centreon\Domain\Service\BrokerConfigurationService;
use CentreonRemote\Infrastructure\Service\ExportService;
use Centreon\Infrastructure\Service\CentcoreConfigService;
use CentreonRemote\Infrastructure\Service\ExporterService;
use Centreon\Domain\Repository\CfgCentreonBrokerRepository;
use Centreon\Infrastructure\Service\CentreonDBManagerService;
use CentreonRemote\Domain\Exporter\ConfigurationExporter;
use CentreonRemote\Infrastructure\Service\ExporterCacheService;
use CentreonRemote\Domain\Service\ConfigurationWizard\LinkedPollerConfigurationService;
use CentreonRemote\Domain\Service\ConfigurationWizard\PollerConfigurationRequestBridge;
use CentreonRemote\Domain\Service\ConfigurationWizard\PollerConnectionConfigurationService;
use CentreonRemote\Domain\Service\ConfigurationWizard\RemoteConnectionConfigurationService;

beforeEach(function (): void {
    $this->provider = new ServiceProvider();
    $this->container = new Container();
    $this->container['centreon.acl'] = $this->createMock(CentreonACL::class);
    $this->container['centreon.config'] = $this->createMock(CentcoreConfigService::class);
    $this->container['realtime_db'] = new CentreonDB();
    $this->container['configuration_db'] = new CentreonDB();
    $this->container['configuration_db']->addResultSet('SELECT * FROM informations WHERE `key` = :key LIMIT 1', []);
    $this->container['rest_http'] = $this->createMock(CentreonRestHttp::class);
    $locator = new ServiceLocator($this->container, ['realtime_db', 'configuration_db']);
    $this->container[\Centreon\ServiceProvider::CENTREON_DB_MANAGER] = new CentreonDBManagerService($locator);
    $this->container[\Centreon\ServiceProvider::CENTREON_WEBSERVICE] = new class {
        public function add(): self
        {
            return $this;
        }
    };
    $this->container[\Centreon\ServiceProvider::CENTREON_CLAPI] = new class {
        public function add(): self
        {
            return $this;
        }
    };
    $this->container['yml.config'] = function (): array {
        return [];
    };

    $this->container[\Centreon\ServiceProvider::CENTREON_BROKER_REPOSITORY] = new CfgCentreonBrokerRepository(
        $this->container['configuration_db']
    );
    $this->container['centreon.broker_configuration_service'] = new BrokerConfigurationService();
    $this->provider->register($this->container);
});

it('check services by list', function () {
    $checkList = [
        'centreon.notifymaster' => NotifyMasterService::class,
        'centreon.taskservice' => TaskService::class,
        'centreon_remote.informations_service' => InformationsService::class,
        'centreon_remote.remote_connection_service' => RemoteConnectionConfigurationService::class,
        'centreon_remote.poller_connection_service' => PollerConnectionConfigurationService::class,
        'centreon_remote.poller_config_service' => LinkedPollerConfigurationService::class,
        'centreon_remote.poller_config_bridge' => PollerConfigurationRequestBridge::class,

        'centreon_remote.export' => ExportService::class,
        'centreon_remote.exporter' => ExporterService::class,
        'centreon_remote.exporter.cache' => ExporterCacheService::class,
    ];

    // check list of services
    foreach ($checkList as $serviceName => $className) {
        $this->assertTrue($this->container->offsetExists($serviceName));

        $service = $this->container->offsetGet($serviceName);

        expect($service)->toBeInstanceOf($className);
    }
});

it('check exporters by list', function () {
    $checkList = [
        ConfigurationExporter::class,
    ];

    $exporter = $this->container['centreon_remote.exporter'];

    // check list of exporters
    foreach ($checkList as $className) {
        $name = $className::getName();
        expect($exporter->has($name))->toBeTrue();

        $data = $exporter->get($className::getName());
        expect($data['name'])->toBe($name);
        expect($data['classname'])->toBe($className);

        $object = $data['factory']($this->container);
        expect($object)->toBeInstanceOf($className);
    }
});

it ('test provider order', function () {
    expect($this->provider::order())->toBeGreaterThanOrEqual(1);
    expect($this->provider::order())->toBeLessThanOrEqual(20);
});
