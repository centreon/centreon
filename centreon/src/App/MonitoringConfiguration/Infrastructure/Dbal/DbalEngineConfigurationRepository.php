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

namespace App\MonitoringConfiguration\Infrastructure\Dbal;

use App\MonitoringConfiguration\Domain\Aggregate\EngineConfiguration\EngineConfiguration;
use App\MonitoringConfiguration\Domain\Aggregate\EngineConfiguration\EngineConfigurationId;
use App\MonitoringConfiguration\Domain\Repository\EngineConfigurationRepository;
use App\Shared\Infrastructure\Dbal\DbalRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class DbalEngineConfigurationRepository extends DbalRepository implements EngineConfigurationRepository
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
    ) {
    }

    public function add(EngineConfiguration $engineConfiguration): void
    {
        $this->connection->transactional(function () use ($engineConfiguration): void {
            $this->insertCfgNagios($engineConfiguration);
            $this->insertCfgNagiosLogger($engineConfiguration);
            $this->insertCfgNagiosBrokerModule($engineConfiguration);
        });
    }

    private function insertCfgNagios(EngineConfiguration $cfg): void
    {
        $check = $cfg->checkExecution;
        $flap = $cfg->freshnessAndFlap;
        $log = $cfg->logging;
        $retention = $cfg->retention;
        $sched = $cfg->scheduling;
        $broker = $cfg->broker;
        $misc = $cfg->misc;

        $qb = $this->connection->createQueryBuilder();

        $qb->insert('cfg_nagios')
            ->values([
                'nagios_name' => ':nagios_name',
                'nagios_server_id' => ':nagios_server_id',
                'nagios_activate' => ':nagios_activate',
                'cfg_dir' => ':cfg_dir',
                'log_file' => ':log_file',
                'status_file' => ':status_file',
                'status_update_interval' => ':status_update_interval',
                'check_external_commands' => ':check_external_commands',
                'command_check_interval' => ':command_check_interval',
                'command_file' => ':command_file',
                'external_command_buffer_slots' => ':external_command_buffer_slots',
                'nagios_comment' => ':nagios_comment',
                'enable_notifications' => ':enable_notifications',
                'execute_service_checks' => ':execute_service_checks',
                'accept_passive_service_checks' => ':accept_passive_service_checks',
                'execute_host_checks' => ':execute_host_checks',
                'accept_passive_host_checks' => ':accept_passive_host_checks',
                'enable_event_handlers' => ':enable_event_handlers',
                'enable_predictive_host_dependency_checks' => ':enable_predictive_host_dependency_checks',
                'enable_predictive_service_dependency_checks' => ':enable_predictive_service_dependency_checks',
                'host_down_disable_service_checks' => ':host_down_disable_service_checks',
                'soft_state_dependencies' => ':soft_state_dependencies',
                'check_for_orphaned_services' => ':check_for_orphaned_services',
                'check_for_orphaned_hosts' => ':check_for_orphaned_hosts',
                'check_service_freshness' => ':check_service_freshness',
                'check_host_freshness' => ':check_host_freshness',
                'additional_freshness_latency' => ':additional_freshness_latency',
                'enable_flap_detection' => ':enable_flap_detection',
                'low_service_flap_threshold' => ':low_service_flap_threshold',
                'high_service_flap_threshold' => ':high_service_flap_threshold',
                'low_host_flap_threshold' => ':low_host_flap_threshold',
                'high_host_flap_threshold' => ':high_host_flap_threshold',
                'use_syslog' => ':use_syslog',
                'log_notifications' => ':log_notifications',
                'log_service_retries' => ':log_service_retries',
                'log_host_retries' => ':log_host_retries',
                'log_event_handlers' => ':log_event_handlers',
                'log_external_commands' => ':log_external_commands',
                'log_passive_checks' => ':log_passive_checks',
                'log_pid' => ':log_pid',
                'debug_file' => ':debug_file',
                'debug_level' => ':debug_level',
                'debug_level_opt' => ':debug_level_opt',
                'debug_verbosity' => ':debug_verbosity',
                'max_debug_file_size' => ':max_debug_file_size',
                'retain_state_information' => ':retain_state_information',
                'state_retention_file' => ':state_retention_file',
                'retention_update_interval' => ':retention_update_interval',
                'use_retained_program_state' => ':use_retained_program_state',
                'use_retained_scheduling_info' => ':use_retained_scheduling_info',
                'event_broker_options' => ':event_broker_options',
                'broker_module_cfg_file' => ':broker_module_cfg_file',
                'sleep_time' => ':sleep_time',
                'host_inter_check_delay_method' => ':host_inter_check_delay_method',
                'service_inter_check_delay_method' => ':service_inter_check_delay_method',
                'service_interleave_factor' => ':service_interleave_factor',
                'max_concurrent_checks' => ':max_concurrent_checks',
                'max_service_check_spread' => ':max_service_check_spread',
                'max_host_check_spread' => ':max_host_check_spread',
                'check_result_reaper_frequency' => ':check_result_reaper_frequency',
                'auto_reschedule_checks' => ':auto_reschedule_checks',
                'cached_host_check_horizon' => ':cached_host_check_horizon',
                'cached_service_check_horizon' => ':cached_service_check_horizon',
                'service_check_timeout' => ':service_check_timeout',
                'host_check_timeout' => ':host_check_timeout',
                'event_handler_timeout' => ':event_handler_timeout',
                'notification_timeout' => ':notification_timeout',
                'enable_environment_macros' => ':enable_environment_macros',
                'date_format' => ':date_format',
                'illegal_object_name_chars' => ':illegal_object_name_chars',
                'illegal_macro_output_chars' => ':illegal_macro_output_chars',
                'use_regexp_matching' => ':use_regexp_matching',
                'use_true_regexp_matching' => ':use_true_regexp_matching',
                'admin_email' => ':admin_email',
                'admin_pager' => ':admin_pager',
                'logger_version' => ':logger_version',
                'instance_heartbeat_interval' => ':instance_heartbeat_interval',
                'enable_macros_filter' => ':enable_macros_filter',
                'macros_filter' => ':macros_filter',
            ])
            ->setParameter('nagios_name', $cfg->name)
            ->setParameter('nagios_server_id', $cfg->pollerId->value)
            ->setParameter('nagios_activate', $cfg->isActivated ? '1' : '0')
            ->setParameter('cfg_dir', $misc->configDirectory)
            ->setParameter('log_file', $misc->logFile)
            ->setParameter('status_file', $misc->statusFile)
            ->setParameter('status_update_interval', $misc->statusUpdateInterval)
            ->setParameter('check_external_commands', $misc->checkExternalCommands ? '1' : '0')
            ->setParameter('command_check_interval', $misc->commandCheckInterval)
            ->setParameter('command_file', $misc->commandFile)
            ->setParameter('external_command_buffer_slots', $misc->externalCommandBufferSlots)
            ->setParameter('nagios_comment', $misc->comment)
            ->setParameter('enable_notifications', $check->enableNotifications ? '1' : '0')
            ->setParameter('execute_service_checks', $check->executeServiceChecks ? '1' : '0')
            ->setParameter('accept_passive_service_checks', $check->acceptPassiveServiceChecks ? '1' : '0')
            ->setParameter('execute_host_checks', $check->executeHostChecks ? '1' : '0')
            ->setParameter('accept_passive_host_checks', $check->acceptPassiveHostChecks ? '1' : '0')
            ->setParameter('enable_event_handlers', $check->enableEventHandlers ? '1' : '0')
            ->setParameter('enable_predictive_host_dependency_checks', $check->enablePredictiveHostDependencyChecks ? '1' : '0')
            ->setParameter('enable_predictive_service_dependency_checks', $check->enablePredictiveServiceDependencyChecks ? '1' : '0')
            ->setParameter('host_down_disable_service_checks', $check->hostDownDisableServiceChecks ? '1' : '0')
            ->setParameter('soft_state_dependencies', $check->softStateDependencies ? '1' : '0')
            ->setParameter('check_for_orphaned_services', $check->checkForOrphanedServices ? '1' : '0')
            ->setParameter('check_for_orphaned_hosts', $check->checkForOrphanedHosts ? '1' : '0')
            ->setParameter('check_service_freshness', $flap->checkServiceFreshness ? '1' : '0')
            ->setParameter('check_host_freshness', $flap->checkHostFreshness ? '1' : '0')
            ->setParameter('additional_freshness_latency', $flap->additionalFreshnessLatency)
            ->setParameter('enable_flap_detection', $flap->enableFlapDetection ? '1' : '0')
            ->setParameter('low_service_flap_threshold', (string) $flap->lowServiceFlapThreshold)
            ->setParameter('high_service_flap_threshold', (string) $flap->highServiceFlapThreshold)
            ->setParameter('low_host_flap_threshold', (string) $flap->lowHostFlapThreshold)
            ->setParameter('high_host_flap_threshold', (string) $flap->highHostFlapThreshold)
            ->setParameter('use_syslog', $log->useSyslog ? '1' : '0')
            ->setParameter('log_notifications', $log->logNotifications ? '1' : '0')
            ->setParameter('log_service_retries', $log->logServiceRetries ? '1' : '0')
            ->setParameter('log_host_retries', $log->logHostRetries ? '1' : '0')
            ->setParameter('log_event_handlers', $log->logEventHandlers ? '1' : '0')
            ->setParameter('log_external_commands', $log->logExternalCommands ? '1' : '0')
            ->setParameter('log_passive_checks', $log->logPassiveChecks ? '1' : '0')
            ->setParameter('log_pid', $log->logPid ? '1' : '0')
            ->setParameter('debug_file', $log->debugFile)
            ->setParameter('debug_level', $log->debugLevel)
            ->setParameter('debug_level_opt', $log->debugLevelOpt)
            ->setParameter('debug_verbosity', $log->debugVerbosity)
            ->setParameter('max_debug_file_size', $log->maxDebugFileSize)
            ->setParameter('retain_state_information', $retention->retainStateInformation ? '1' : '0')
            ->setParameter('state_retention_file', $retention->stateRetentionFile)
            ->setParameter('retention_update_interval', $retention->retentionUpdateInterval)
            ->setParameter('use_retained_program_state', $retention->useRetainedProgramState ? '1' : '0')
            ->setParameter('use_retained_scheduling_info', $retention->useRetainedSchedulingInfo ? '1' : '0')
            ->setParameter('event_broker_options', $broker->eventBrokerOptions)
            ->setParameter('broker_module_cfg_file', $broker->brokerModuleCfgFile)
            ->setParameter('sleep_time', (string) $sched->sleepTime)
            ->setParameter('host_inter_check_delay_method', $sched->hostInterCheckDelayMethod)
            ->setParameter('service_inter_check_delay_method', $sched->serviceInterCheckDelayMethod)
            ->setParameter('service_interleave_factor', $sched->serviceInterleaveFactor)
            ->setParameter('max_concurrent_checks', $sched->maxConcurrentChecks)
            ->setParameter('max_service_check_spread', $sched->maxServiceCheckSpread)
            ->setParameter('max_host_check_spread', $sched->maxHostCheckSpread)
            ->setParameter('check_result_reaper_frequency', $sched->checkResultReaperFrequency)
            ->setParameter('auto_reschedule_checks', $sched->autoRescheduleChecks ? '1' : '0')
            ->setParameter('cached_host_check_horizon', $sched->cachedHostCheckHorizon)
            ->setParameter('cached_service_check_horizon', $sched->cachedServiceCheckHorizon)
            ->setParameter('service_check_timeout', $sched->serviceCheckTimeout)
            ->setParameter('host_check_timeout', $sched->hostCheckTimeout)
            ->setParameter('event_handler_timeout', $sched->eventHandlerTimeout)
            ->setParameter('notification_timeout', $sched->notificationTimeout)
            ->setParameter('enable_environment_macros', $sched->enableEnvironmentMacros ? '1' : '0')
            ->setParameter('date_format', $misc->dateFormat)
            ->setParameter('illegal_object_name_chars', $misc->illegalObjectNameChars)
            ->setParameter('illegal_macro_output_chars', $misc->illegalMacroOutputChars)
            ->setParameter('use_regexp_matching', $misc->useRegexpMatching ? '1' : '0')
            ->setParameter('use_true_regexp_matching', $misc->useTrueRegexpMatching ? '1' : '0')
            ->setParameter('admin_email', $misc->adminEmail)
            ->setParameter('admin_pager', $misc->adminPager)
            ->setParameter('logger_version', $misc->loggerVersion)
            ->setParameter('instance_heartbeat_interval', $misc->instanceHeartbeatInterval)
            ->setParameter('enable_macros_filter', $misc->enableMacrosFilter ? '1' : '0')
            ->setParameter('macros_filter', $misc->macrosFilter)
            ->executeStatement();

        $cfgNagiosId = (int) $this->connection->lastInsertId();

        if ($cfgNagiosId === 0) {
            throw new \RuntimeException('Unable to retrieve last insert ID for "cfg_nagios".');
        }

        $this->setId($cfg, new EngineConfigurationId($cfgNagiosId));
    }

    private function insertCfgNagiosLogger(EngineConfiguration $cfg): void
    {
        $logger = $cfg->logging->loggerConfiguration;

        $qb = $this->connection->createQueryBuilder();

        $qb->insert('cfg_nagios_logger')
            ->values([
                'cfg_nagios_id' => ':cfg_nagios_id',
                'log_v2_logger' => ':log_v2_logger',
                'log_level_functions' => ':log_level_functions',
                'log_level_config' => ':log_level_config',
                'log_level_events' => ':log_level_events',
                'log_level_checks' => ':log_level_checks',
                'log_level_notifications' => ':log_level_notifications',
                'log_level_eventbroker' => ':log_level_eventbroker',
                'log_level_external_command' => ':log_level_external_command',
                'log_level_commands' => ':log_level_commands',
                'log_level_downtimes' => ':log_level_downtimes',
                'log_level_comments' => ':log_level_comments',
                'log_level_macros' => ':log_level_macros',
                'log_level_process' => ':log_level_process',
                'log_level_runtime' => ':log_level_runtime',
                'log_level_otl' => ':log_level_otl',
            ])
            ->setParameter('cfg_nagios_id', $cfg->id()->value)
            ->setParameter('log_v2_logger', $logger->loggerType->value)
            ->setParameter('log_level_functions', $logger->functionsLevel->value)
            ->setParameter('log_level_config', $logger->configLevel->value)
            ->setParameter('log_level_events', $logger->eventsLevel->value)
            ->setParameter('log_level_checks', $logger->checksLevel->value)
            ->setParameter('log_level_notifications', $logger->notificationsLevel->value)
            ->setParameter('log_level_eventbroker', $logger->eventbrokerLevel->value)
            ->setParameter('log_level_external_command', $logger->externalCommandLevel->value)
            ->setParameter('log_level_commands', $logger->commandsLevel->value)
            ->setParameter('log_level_downtimes', $logger->downtimesLevel->value)
            ->setParameter('log_level_comments', $logger->commentsLevel->value)
            ->setParameter('log_level_macros', $logger->macrosLevel->value)
            ->setParameter('log_level_process', $logger->processLevel->value)
            ->setParameter('log_level_runtime', $logger->runtimeLevel->value)
            ->setParameter('log_level_otl', $logger->otlLevel->value)
            ->executeStatement();
    }

    private function insertCfgNagiosBrokerModule(EngineConfiguration $cfg): void
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->insert('cfg_nagios_broker_module')
            ->values([
                'cfg_nagios_id' => ':cfg_nagios_id',
                'broker_module' => ':broker_module',
            ])
            ->setParameter('cfg_nagios_id', $cfg->id()->value)
            ->setParameter('broker_module', $cfg->broker->brokerModule)
            ->executeStatement();
    }
}
