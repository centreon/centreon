<?php

/*
 * Copyright 2005-2023 Centreon
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

// file centreon.config.php may not exist in test environment

use App\Kernel;
use Core\AdditionalConnectorConfiguration\Application\Repository\ReadAccRepositoryInterface;
use Core\AgentConfiguration\Application\Repository\ReadAgentConfigurationRepositoryInterface;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\Security\Token\Application\Repository\ReadTokenRepositoryInterface;
use Pimple\Container;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

$configFile = realpath(__DIR__ . "/../../../config/centreon.config.php");
if ($configFile !== false) {
    require_once $configFile;
}

require_once __DIR__ . '/abstract/object.class.php';
require_once __DIR__ . '/abstract/objectJSON.class.php';
require_once __DIR__ . '/backend.class.php';
require_once __DIR__ . '/broker.class.php';
require_once __DIR__ . '/command.class.php';
require_once __DIR__ . '/connector.class.php';
require_once __DIR__ . '/contact.class.php';
require_once __DIR__ . '/contactgroup.class.php';
require_once __DIR__ . '/dependency.class.php';
require_once __DIR__ . '/engine.class.php';
require_once __DIR__ . '/escalation.class.php';
require_once __DIR__ . '/host.class.php';
require_once __DIR__ . '/hostcategory.class.php';
require_once __DIR__ . '/hostgroup.class.php';
require_once __DIR__ . '/hosttemplate.class.php';
require_once __DIR__ . '/macro.class.php';
require_once __DIR__ . '/media.class.php';
require_once __DIR__ . '/meta_command.class.php';
require_once __DIR__ . '/meta_host.class.php';
require_once __DIR__ . '/meta_service.class.php';
require_once __DIR__ . '/meta_timeperiod.class.php';
require_once __DIR__ . '/resource.class.php';
require_once __DIR__ . '/service.class.php';
require_once __DIR__ . '/servicecategory.class.php';
require_once __DIR__ . '/servicegroup.class.php';
require_once __DIR__ . '/servicetemplate.class.php';
require_once __DIR__ . '/severity.class.php';
require_once __DIR__ . '/timeperiod.class.php';
require_once __DIR__ . '/timezone.class.php';
require_once __DIR__ . '/vault.class.php';
require_once __DIR__ . '/AdditionalConnectorVmWareV6.class.php';
require_once __DIR__ . '/AgentConfiguration.php';

/**
 * Class
 *
 * @class Generate
 */
class Generate
{
    private const GENERATION_FOR_ENGINE = 1;
    private const GENERATION_FOR_BROKER = 2;

    /** @var array */
    private $poller_cache = [];
    /** @var Backend|null */
    private $backend_instance = null;
    /** @var null */
    private $current_poller = null;
    /** @var null */
    private $installed_modules = null;
    /** @var null */
    private $module_objects = null;
    /** @var Container|null */
    protected $dependencyInjector = null;

    private ReadAccRepositoryInterface $readAdditionalConnectorRepository;

    private ReadAgentConfigurationRepositoryInterface $readAgentConfigurationRepository;
    private ReadTokenRepositoryInterface $readTokenRepository;

    private ReadHostRepositoryInterface $readHostRepository;

    /**
     * Generate constructor
     *
     * @param Container $dependencyInjector
     */
    public function __construct(Container $dependencyInjector)
    {
        $this->dependencyInjector = $dependencyInjector;
        $this->backend_instance = Backend::getInstance($this->dependencyInjector);
        $kernel = Kernel::createForWeb();
        $this->readAdditionalConnectorRepository = $kernel->getContainer()->get(ReadAccRepositoryInterface::class)
            ?? throw new \Exception('ReadAccRepositoryInterface not found');
        $this->readAgentConfigurationRepository = $kernel->getContainer()
            ->get(ReadAgentConfigurationRepositoryInterface::class)
            ?? throw new \Exception('ReadAgentConfigurationRepositoryInterface not found');
        $this->readTokenRepository = $kernel->getContainer()
            ->get(ReadTokenRepositoryInterface::class)
            ?? throw new \Exception('ReadTokenRepositoryInterface not found');
        $this->readHostRepository = $kernel->getContainer()
            ->get(ReadHostRepositoryInterface::class)
            ?? throw new \Exception('ReadHostRepositoryInterface not found');
    }

    /**
     * Insert services in index_data
     *
     * @param bool $isLocalhost (FALSE by default)
     *
     * @return void
     * @throws PDOException
     */
    private function generateIndexData($isLocalhost = false): void
    {
        $serviceInstance = Service::getInstance($this->dependencyInjector);
        $hostInstance = Host::getInstance($this->dependencyInjector);
        $services = $serviceInstance->getGeneratedServices();

        $bulkLimit = 2000;

        $valuesQueries = [];
        $bindParams = [];
        $bulkCount = 0;

        $bulkInsert = function () use (&$valuesQueries, &$bindParams, &$bulkCount): void {
            $stmt = $this->backend_instance->db_cs->prepare(
                'INSERT INTO index_data (host_id, service_id, host_name, service_description) VALUES '
                . implode(',', $valuesQueries)
                . ' ON DUPLICATE KEY UPDATE '
                . ' host_name=VALUES(host_name), service_description=VALUES(service_description) '
            );

            foreach ($bindParams as $bindKey => [$bindValue, $bindType]) {
                $stmt->bindValue($bindKey, $bindValue, $bindType);
            }

            $stmt->execute();

            $valuesQueries = [];
            $bindParams = [];
            $bulkCount = 0;
        };

        foreach ($services as $hostId => &$values) {
            $hostName = $hostInstance->getString($hostId, 'host_name');
            foreach ($values as $serviceId) {
                $serviceDescription = $serviceInstance->getString($serviceId, 'service_description');
                $bindParams[":host_id_{$hostId}"] = [$hostId, PDO::PARAM_INT];
                $bindParams[":service_id_{$serviceId}"] = [$serviceId, PDO::PARAM_INT];
                $bindParams[":host_name_{$hostId}"] = [$hostName, PDO::PARAM_STR];
                $bindParams[":service_description_{$serviceId}"] = [$serviceDescription, PDO::PARAM_STR];
                $valuesQueries[] = "(
                    :host_id_{$hostId},
                    :service_id_{$serviceId},
                    :host_name_{$hostId},
                    :service_description_{$serviceId}
                )";
                $bulkCount++;
                if ($bulkCount === $bulkLimit) {
                    $bulkInsert();
                }
            }
        }

        # Meta services
        if ($isLocalhost) {
            $metaServices = MetaService::getInstance($this->dependencyInjector)->getMetaServices();
            $hostId = MetaHost::getInstance($this->dependencyInjector)->getHostIdByHostName('_Module_Meta');
            foreach ($metaServices as $metaId => $metaService) {
                $bindParams[":host_id_{$hostId}"] = [$hostId, PDO::PARAM_INT];
                $bindParams[":meta_service_id_{$metaId}"] = [$metaService['service_id'], PDO::PARAM_INT];
                $bindParams[":host_name_{$hostId}"] = ['_Module_Meta', PDO::PARAM_STR];
                $bindParams[":meta_service_description_{$metaId}"] = ['meta_' . $metaId, PDO::PARAM_STR];
                $valuesQueries[] = "(
                    :host_id_{$hostId},
                    :meta_service_id_{$metaId},
                    :host_name_{$hostId},
                    :meta_service_description_{$metaId}
                )";
                $bulkCount++;
                if ($bulkCount === $bulkLimit) {
                    $bulkInsert();
                }
            }
        }

        if ($bulkCount > 0) {
            $bulkInsert();
        }
    }

    /**
     * Insert services created by modules in index_data
     *
     * @param bool $isLocalhost (FALSE by default)
     *
     * @return void
     * @throws PDOException
     */
    private function generateModulesIndexData($isLocalhost = false): void
    {
        if (is_null($this->module_objects)) {
            $this->getModuleObjects();
        }
        if (is_array($this->module_objects)) {
            foreach ($this->module_objects as $module_object) {
                $moduleInstance = $module_object::getInstance($this->dependencyInjector);
                if (
                    $moduleInstance->isEngineObject() == true
                    && method_exists($moduleInstance, 'generateModuleIndexData')
                ) {
                    $moduleInstance->generateModuleIndexData($isLocalhost);
                }
            }
        }
    }

    /**
     * @param $poller_id
     *
     * @return void
     * @throws PDOException
     */
    private function getPollerFromId($poller_id): void
    {
        $query = "SELECT id, localhost,  centreonconnector_path FROM nagios_server " .
            "WHERE id = :poller_id";
        $stmt = $this->backend_instance->db->prepare($query);
        $stmt->bindParam(':poller_id', $poller_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->current_poller = array_pop($result);
        if (is_null($this->current_poller)) {
            throw new Exception("Cannot find poller id '" . $poller_id . "'");
        }
    }

    /**
     * @param $poller_name
     *
     * @return void
     * @throws PDOException
     */
    private function getPollerFromName($poller_name): void
    {
        $query = "SELECT id, localhost, centreonconnector_path FROM nagios_server " .
            "WHERE name = :poller_name";
        $stmt = $this->backend_instance->db->prepare($query);
        $stmt->bindParam(':poller_name', $poller_name, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->current_poller = array_pop($result);
        if (is_null($this->current_poller)) {
            throw new Exception("Cannot find poller name '" . $poller_name . "'");
        }
    }

    /**
     * @return void
     * @throws Exception
     */
    public function resetObjectsEngine(): void
    {
        Host::getInstance($this->dependencyInjector)->reset();
        HostTemplate::getInstance($this->dependencyInjector)->reset();
        Service::getInstance($this->dependencyInjector)->reset();
        ServiceTemplate::getInstance($this->dependencyInjector)->reset();
        Command::getInstance($this->dependencyInjector)->reset();
        Contact::getInstance($this->dependencyInjector)->reset();
        Contactgroup::getInstance($this->dependencyInjector)->reset();
        Hostgroup::getInstance($this->dependencyInjector)->reset();
        HostCategory::getInstance($this->dependencyInjector)->reset();
        Servicegroup::getInstance($this->dependencyInjector)->reset();
        Severity::getInstance($this->dependencyInjector)->reset();
        ServiceCategory::getInstance($this->dependencyInjector)->reset();
        Timeperiod::getInstance($this->dependencyInjector)->reset();
        Escalation::getInstance($this->dependencyInjector)->reset();
        Dependency::getInstance($this->dependencyInjector)->reset();
        MetaCommand::getInstance($this->dependencyInjector)->reset();
        MetaTimeperiod::getInstance($this->dependencyInjector)->reset();
        MetaService::getInstance($this->dependencyInjector)->reset();
        MetaHost::getInstance($this->dependencyInjector)->reset();
        Connector::getInstance($this->dependencyInjector)->reset();
        Resource::getInstance($this->dependencyInjector)->reset();
        Engine::getInstance($this->dependencyInjector)->reset();
        Broker::getInstance($this->dependencyInjector)->reset();

        (new AdditionalConnectorVmWareV6(
            $this->dependencyInjector,
            $this->backend_instance,
            $this->readAdditionalConnectorRepository
        ))->reset();
        (new AgentConfiguration(
            $this->backend_instance,
            $this->readAgentConfigurationRepository,
            $this->readTokenRepository,
            $this->readHostRepository
        ))->reset();
        $this->resetModuleObjects();
    }

    /**
     * @param $username
     *
     * @return void
     * @throws LogicException
     * @throws PDOException
     * @throws ServiceCircularReferenceException
     * @throws ServiceNotFoundException
     */
    private function configPoller($username = 'unknown'): void
    {
        $this->backend_instance->setUserName($username);
        $this->backend_instance->initPath($this->current_poller['id']);
        $this->backend_instance->setPollerId($this->current_poller['id']);
        $this->resetObjectsEngine();
        (new AgentConfiguration(
            $this->backend_instance,
            $this->readAgentConfigurationRepository,
            $this->readTokenRepository,
            $this->readHostRepository
        ))->generateFromPollerId($this->current_poller['id']);

        Vault::getInstance($this->dependencyInjector)->generateFromPoller($this->current_poller);
        Host::getInstance($this->dependencyInjector)->generateFromPollerId(
            $this->current_poller['id'],
            $this->current_poller['localhost']
        );
        $this->generateModuleObjects(self::GENERATION_FOR_ENGINE);

        Engine::getInstance($this->dependencyInjector)->generateFromPoller($this->current_poller);
        $this->backend_instance->movePath($this->current_poller['id']);

        $this->backend_instance->initPath($this->current_poller['id'], 2);
        $this->generateModuleObjects(self::GENERATION_FOR_BROKER);
        Broker::getInstance($this->dependencyInjector)->generateFromPoller($this->current_poller);
        $this->backend_instance->movePath($this->current_poller['id']);
        $this->generateIndexData($this->current_poller['localhost'] === '1');
        $this->generateModulesIndexData($this->current_poller['localhost'] === '1');

        $this->backend_instance->initPath($this->current_poller['id'], 3);
        (new AdditionalConnectorVmWareV6(
            $this->dependencyInjector,
            $this->backend_instance,
            $this->readAdditionalConnectorRepository
        ))->generateFromPollerId($this->current_poller['id']);
        $this->backend_instance->movePath($this->current_poller['id']);
    }

    /**
     * @param $poller_name
     *
     * @return void
     * @throws Exception
     */
    public function configPollerFromName($poller_name): void
    {
        try {
            $this->getPollerFromName($poller_name);
            $this->configPoller();
        } catch (Exception $e) {
            throw new Exception('Exception received : ' . $e->getMessage() . " [file: " . $e->getFile() .
                "] [line: " . $e->getLine() . "]\n");
            $this->backend_instance->cleanPath();
        }
    }

    /**
     * @param $poller_id
     * @param $username
     *
     * @return void
     * @throws Exception
     */
    public function configPollerFromId($poller_id, $username = 'unknown'): void
    {
        try {
            if (is_null($this->current_poller)) {
                $this->getPollerFromId($poller_id);
            }
            $this->configPoller($username);
        } catch (Exception $e) {
            throw new Exception('Exception received : ' . $e->getMessage() . " [file: " . $e->getFile() .
                "] [line: " . $e->getLine() . "]\n");
            $this->backend_instance->cleanPath();
        }
    }

    /**
     * @param $username
     *
     * @return void
     * @throws PDOException
     */
    public function configPollers($username = 'unknown'): void
    {
        $query = "SELECT id, localhost, centreonconnector_path FROM " .
            "nagios_server WHERE ns_activate = '1'";
        $stmt = $this->backend_instance->db->prepare($query);
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $value) {
            $this->current_poller = $value;
            $this->configPollerFromId($this->current_poller['id'], $username);
        }
    }

    /**
     * @return void|null
     * @throws PDOException
     */
    public function getInstalledModules()
    {
        if (!is_null($this->installed_modules)) {
            return $this->installed_modules;
        }
        $this->installed_modules = [];
        $stmt = $this->backend_instance->db->prepare("SELECT name FROM modules_informations");
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $value) {
            $this->installed_modules[] = $value['name'];
        }
    }

    /**
     * @return void
     * @throws PDOException
     */
    public function getModuleObjects(): void
    {
        $this->getInstalledModules();

        foreach ($this->installed_modules as $module) {
            if ($files = glob(_CENTREON_PATH_ . 'www/modules/' . $module . '/generate_files/*.class.php')) {
                foreach ($files as $full_file) {
                    require_once $full_file;
                    $file_name = str_replace('.class.php', '', basename($full_file));
                    if (class_exists(ucfirst($file_name))) {
                        $this->module_objects[] = ucfirst($file_name);
                    }
                }
            }
        }
    }

    /**
     * @param int $type (1: engine, 2: broker)
     *
     * @return void
     * @throws PDOException
     */
    public function generateModuleObjects(int $type = self::GENERATION_FOR_ENGINE): void
    {
        if (is_null($this->module_objects)) {
            $this->getModuleObjects();
        }
        if (is_array($this->module_objects)) {
            foreach ($this->module_objects as $module_object) {
                $externalModule = $module_object::getInstance($this->dependencyInjector);
                if (
                    $externalModule instanceof ExternalModuleGenerationInterface
                    && (
                        ($type === self::GENERATION_FOR_ENGINE && $externalModule->isEngineObject() === true)
                        || ($type === self::GENERATION_FOR_BROKER && $externalModule->isBrokerObject() === true)
                    )
                ) {
                    $externalModule->generateFromPollerId(
                        (int) $this->current_poller['id'],
                        (bool) $this->current_poller['localhost']
                    );
                }
            }
        }
    }

    /**
     * @return void
     * @throws PDOException
     */
    public function resetModuleObjects(): void
    {
        if (is_null($this->module_objects)) {
            $this->getModuleObjects();
        }
        if (is_array($this->module_objects)) {
            foreach ($this->module_objects as $module_object) {
                $externalModule = $module_object::getInstance($this->dependencyInjector);
                if ($externalModule instanceof ExternalModuleGenerationInterface) {
                    $externalModule->reset();
                }
            }
        }
    }

    /**
     * Reset the cache and the instance
     */
    public function reset(): void
    {
        $this->poller_cache = [];
        $this->current_poller = null;
        $this->installed_modules = null;
        $this->module_objects = null;
    }
}
