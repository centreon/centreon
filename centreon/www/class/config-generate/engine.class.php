<?php
/*
 * Copyright 2005-2019 Centreon
 * Centreon is developed by : Julien Mathis and Romain Le Merlus under
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

use App\Kernel;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * Class
 *
 * @class Engine
 */
class Engine extends AbstractObject
{
    /** @var array */
    public $cfg_file;
    /** @var array|null */
    protected $engine = null;
    /** @var string|null */
    protected $generate_filename = null; # it's in 'cfg_nagios' table
    /** @var string */
    protected string $object_name;
    /** @var string */
    protected $attributes_select = '
        nagios_id,
        use_timezone,
        cfg_dir,
        cfg_file as cfg_filename,
        log_file,
        status_file,
        status_update_interval,
        external_command_buffer_slots,
        command_check_interval,
        command_file,
        state_retention_file,
        retention_update_interval,
        sleep_time,
        service_inter_check_delay_method,
        host_inter_check_delay_method,
        service_interleave_factor,
        max_concurrent_checks,
        max_service_check_spread,
        max_host_check_spread,
        check_result_reaper_frequency,
        auto_rescheduling_interval,
        auto_rescheduling_window,
        enable_flap_detection,
        low_service_flap_threshold,
        high_service_flap_threshold,
        low_host_flap_threshold,
        high_host_flap_threshold,
        service_check_timeout,
        host_check_timeout,
        event_handler_timeout,
        notification_timeout,
        service_freshness_check_interval,
        host_freshness_check_interval,
        instance_heartbeat_interval,
        date_format,
        illegal_object_name_chars,
        illegal_macro_output_chars,
        admin_email,
        admin_pager,
        event_broker_options,
        cached_host_check_horizon,
        cached_service_check_horizon,
        additional_freshness_latency,
        debug_file,
        debug_level,
        debug_level_opt,
        debug_verbosity,
        max_debug_file_size,
        log_pid,
        global_host_event_handler as global_host_event_handler_id,
        global_service_event_handler as global_service_event_handler_id,
        enable_notifications,
        execute_service_checks,
        accept_passive_service_checks,
        execute_host_checks,
        accept_passive_host_checks,
        enable_event_handlers,
        check_external_commands,
        use_retained_program_state,
        use_retained_scheduling_info,
        use_syslog,
        log_notifications,
        log_service_retries,
        log_host_retries,
        log_event_handlers,
        log_external_commands,
        log_passive_checks,
        auto_reschedule_checks,
        soft_state_dependencies,
        check_for_orphaned_services,
        check_for_orphaned_hosts,
        check_service_freshness,
        check_host_freshness,
        use_regexp_matching,
        use_true_regexp_matching,
        enable_predictive_host_dependency_checks,
        enable_predictive_service_dependency_checks,
        host_down_disable_service_checks,
        enable_environment_macros,
        enable_macros_filter,
        macros_filter,
        logger_version,
        broker_module_cfg_file
    ';
    /** @var string[] */
    protected $attributes_write = [
        'use_timezone',
        'resource_file',
        'log_file',
        'status_file',
        'status_update_interval',
        'external_command_buffer_slots',
        'command_check_interval',
        'command_file',
        'state_retention_file',
        'retention_update_interval',
        'sleep_time',
        'service_inter_check_delay_method',
        'host_inter_check_delay_method',
        'service_interleave_factor',
        'max_concurrent_checks',
        'max_service_check_spread',
        'max_host_check_spread',
        'check_result_reaper_frequency',
        'auto_rescheduling_interval',
        'auto_rescheduling_window',
        'low_service_flap_threshold',
        'high_service_flap_threshold',
        'low_host_flap_threshold',
        'high_host_flap_threshold',
        'service_check_timeout',
        'host_check_timeout',
        'event_handler_timeout',
        'notification_timeout',
        'service_freshness_check_interval',
        'host_freshness_check_interval',
        'date_format',
        'illegal_object_name_chars',
        'illegal_macro_output_chars',
        'admin_email',
        'admin_pager',
        'event_broker_options',
        'cached_host_check_horizon',
        'cached_service_check_horizon',
        'additional_freshness_latency',
        'debug_file',
        'debug_level',
        'debug_verbosity',
        'max_debug_file_size',
        'log_pid', // centengine
        'global_host_event_handler',
        'global_service_event_handler',
        'macros_filter',
        'enable_macros_filter',
        'grpc_port',
        'log_v2_enabled',
        'log_legacy_enabled',
        'log_v2_logger',
        'log_level_functions',
        'log_level_config',
        'log_level_events',
        'log_level_checks',
        'log_level_notifications',
        'log_level_eventbroker',
        'log_level_external_command',
        'log_level_commands',
        'log_level_downtimes',
        'log_level_comments',
        'log_level_macros',
        'log_level_process',
        'log_level_runtime',
        'broker_module_cfg_file',
    ];
    /** @var string[] */
    protected $attributes_default = [
        'instance_heartbeat_interval',
        'enable_notifications',
        'execute_service_checks',
        'accept_passive_service_checks',
        'execute_host_checks',
        'accept_passive_host_checks',
        'enable_event_handlers',
        'check_external_commands',
        'use_retained_program_state',
        'use_retained_scheduling_info',
        'use_syslog',
        'log_notifications',
        'log_service_retries',
        'log_host_retries',
        'log_event_handlers',
        'log_external_commands',
        'log_passive_checks',
        'auto_reschedule_checks',
        'soft_state_dependencies',
        'check_for_orphaned_services',
        'check_for_orphaned_hosts',
        'check_service_freshness',
        'check_host_freshness',
        'enable_flap_detection',
        'use_regexp_matching',
        'use_true_regexp_matching',
        'enable_predictive_host_dependency_checks',
        'enable_predictive_service_dependency_checks',
        'host_down_disable_service_checks',
        'enable_environment_macros',
    ];
    /** @var string[] */
    protected $attributes_array = [
        'cfg_file',
        'broker_module',
        'interval_length',
    ];
    /** @var CentreonDBStatement|null */
    protected $stmt_engine = null;
    /** @var CentreonDBStatement|null */
    protected $stmt_broker = null;
    /** @var CentreonDBStatement|null */
    protected $stmt_interval_length = null;
    /** @var array */
    protected $add_cfg_files = [];

    /**
     * @param $poller_id
     *
     * @return void
     */
    private function buildCfgFile($poller_id): void
    {
        $this->engine['cfg_dir'] = preg_replace('/\/$/', '', $this->engine['cfg_dir']);
        $this->cfg_file = [
            'target' => [
                'cfg_file' => [],
                'path' => $this->engine['cfg_dir'],
                'resource_file' => $this->engine['cfg_dir'] . '/resource.cfg'
            ],
            'debug' => [
                'cfg_file' => [],
                'path' => $this->backend_instance->getEngineGeneratePath() . '/' . $poller_id,
                'resource_file' => $this->backend_instance->getEngineGeneratePath() . '/' . $poller_id . '/resource.cfg'
            ]
        ];

        foreach ($this->cfg_file as &$value) {
            $value['cfg_file'][] = $value['path'] . '/hostTemplates.cfg';
            $value['cfg_file'][] = $value['path'] . '/hosts.cfg';
            $value['cfg_file'][] = $value['path'] . '/serviceTemplates.cfg';
            $value['cfg_file'][] = $value['path'] . '/services.cfg';
            $value['cfg_file'][] = $value['path'] . '/commands.cfg';
            $value['cfg_file'][] = $value['path'] . '/contactgroups.cfg';
            $value['cfg_file'][] = $value['path'] . '/contacts.cfg';
            $value['cfg_file'][] = $value['path'] . '/hostgroups.cfg';
            $value['cfg_file'][] = $value['path'] . '/servicegroups.cfg';
            $value['cfg_file'][] = $value['path'] . '/timeperiods.cfg';
            $value['cfg_file'][] = $value['path'] . '/escalations.cfg';
            $value['cfg_file'][] = $value['path'] . '/dependencies.cfg';
            $value['cfg_file'][] = $value['path'] . '/connectors.cfg';
            $value['cfg_file'][] = $value['path'] . '/meta_commands.cfg';
            $value['cfg_file'][] = $value['path'] . '/meta_timeperiod.cfg';
            $value['cfg_file'][] = $value['path'] . '/meta_host.cfg';
            $value['cfg_file'][] = $value['path'] . '/meta_services.cfg';
            $value['cfg_file'][] = $value['path'] . '/tags.cfg';
            $value['cfg_file'][] = $value['path'] . '/severities.cfg';

            foreach ($this->add_cfg_files as $add_cfg_file) {
                $value['cfg_file'][] = $value['path'] . '/' . $add_cfg_file;
            }
        }
    }

    /**
     * @return void
     * @throws PDOException
     */
    private function getBrokerModules(): void
    {
        $pollerId = $this->engine['nagios_id'];
        if (is_null($this->stmt_broker)) {
            $this->stmt_broker = $this->backend_instance->db->prepare(
                "SELECT broker_module FROM cfg_nagios_broker_module " .
                "WHERE cfg_nagios_id = :id " .
                "ORDER BY bk_mod_id ASC"
            );
        }
        $this->stmt_broker->bindParam(':id', $pollerId, PDO::PARAM_INT);
        $this->stmt_broker->execute();
        $this->engine['broker_module'] = $this->stmt_broker->fetchAll(PDO::FETCH_COLUMN);

        $pollerStmt = $this->backend_instance->db_cs->prepare("SELECT `version` FROM instances WHERE instance_id = :id ");
        $pollerStmt->bindParam(':id', $pollerId, PDO::PARAM_INT);
        $pollerStmt->execute();
        $pollerVersion = $pollerStmt->fetchColumn();

        if ($pollerVersion === false || version_compare($pollerVersion, '25.05.0', '<')) {
            $this->engine['broker_module'][] = '/usr/lib64/nagios/cbmod.so ' . $this->engine['broker_module_cfg_file'];
            unset($this->engine['broker_module_cfg_file']);
        }
    }

    /**
     * @return void
     * @throws PDOException
     */
    private function getIntervalLength(): void
    {
        if (is_null($this->stmt_interval_length)) {
            $this->stmt_interval_length = $this->backend_instance->db->prepare(
                "SELECT `value` FROM options " .
                "WHERE `key` = 'interval_length'"
            );
        }
        $this->stmt_interval_length->execute();
        $this->engine['interval_length'] = $this->stmt_interval_length->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     *  If log V2 enabled, set logger V2 configuration and unset logger legacy elements
     *
     * @return void
     * @throws PDOException
     */
    private function setLoggerCfg(): void
    {
        $this->engine['log_v2_enabled'] = $this->engine['logger_version'] === 'log_v2_enabled' ? 1 : 0;
        $this->engine['log_legacy_enabled'] = $this->engine['logger_version'] === 'log_legacy_enabled' ? 1 : 0;

        if ($this->engine['log_v2_enabled'] === 1) {
            $stmt = $this->backend_instance->db->prepare(
                'SELECT log_v2_logger, log_level_functions, log_level_config, log_level_events, log_level_checks,
                    log_level_notifications, log_level_eventbroker, log_level_external_command, log_level_commands,
                    log_level_downtimes, log_level_comments, log_level_macros, log_level_process, log_level_runtime
                FROM cfg_nagios_logger
                WHERE cfg_nagios_id = :id'
            );
            $stmt->bindParam(':id', $this->engine['nagios_id'], PDO::PARAM_INT);
            $stmt->execute();

            $loggerCfg = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->engine = array_merge($this->engine, $loggerCfg);
        }
    }

    /**
     * @return void
     * @throws LogicException
     * @throws ServiceCircularReferenceException
     * @throws ServiceNotFoundException
     */
    private function setEngineNotificationState(): void
    {
        $kernel = Kernel::createForWeb();
        $featureFlags = $kernel->getContainer()->get(Core\Common\Infrastructure\FeatureFlags::class);

        $this->engine['enable_notifications'] =
            $featureFlags->isEnabled('notification') === false
            && $this->engine['enable_notifications'] === '1'
                ? '1'
                : '0';
    }

    /**
     * @param $poller_id
     *
     * @return void
     * @throws LogicException
     * @throws PDOException
     * @throws ServiceCircularReferenceException
     * @throws ServiceNotFoundException
     */
    private function generate($poller_id): void
    {
        if (is_null($this->stmt_engine)) {
            $this->stmt_engine = $this->backend_instance->db->prepare(
                "SELECT $this->attributes_select FROM cfg_nagios " .
                "WHERE nagios_server_id = :poller_id AND nagios_activate = '1'"
            );
        }
        $this->stmt_engine->bindParam(':poller_id', $poller_id, PDO::PARAM_INT);
        $this->stmt_engine->execute();

        $result = $this->stmt_engine->fetchAll(PDO::FETCH_ASSOC);

        $this->engine = array_pop($result);
        $this->setEngineNotificationState();

        if (is_null($this->engine)) {
            throw new Exception(
                "Cannot get engine configuration for poller id (maybe not activate) '" . $poller_id . "'"
            );
        }

        $this->buildCfgFile($poller_id);
        $this->setLoggerCfg();
        $this->getBrokerModules();
        $this->getIntervalLength();

        $object = $this->engine;

        $timezoneInstance = Timezone::getInstance($this->dependencyInjector);
        $timezone = $timezoneInstance->getTimezoneFromId($object['use_timezone'], true);
        $object['use_timezone'] = null;
        if (! is_null($timezone)) {
            $object['use_timezone'] = ':' . $timezone;
        }

        $command_instance = Command::getInstance($this->dependencyInjector);
        $object['global_host_event_handler']
            = $command_instance->generateFromCommandId($object['global_host_event_handler_id']);
        $object['global_service_event_handler']
            = $command_instance->generateFromCommandId($object['global_service_event_handler_id']);

        $object['grpc_port'] = 50000 + $poller_id;
        $this->generate_filename = 'centengine.DEBUG';
        $object['cfg_file'] = $this->cfg_file['debug']['cfg_file'];
        $object['resource_file'] = $this->cfg_file['debug']['resource_file'];
        $this->generateFile($object);
        $this->close_file();

        $this->generate_filename = $this->engine['cfg_filename'];
        // Need to reset to go in another file
        $object['cfg_file'] = $this->cfg_file['target']['cfg_file'];
        $object['resource_file'] = $this->cfg_file['target']['resource_file'];
        $this->generateFile($object);
        $this->close_file();
    }

    /**
     * @param $poller
     *
     * @return void
     * @throws LogicException
     * @throws PDOException
     * @throws ServiceCircularReferenceException
     * @throws ServiceNotFoundException
     */
    public function generateFromPoller($poller): void
    {
        Connector::getInstance($this->dependencyInjector)->generateObjects($poller['centreonconnector_path']);
        Resource::getInstance($this->dependencyInjector)->generateFromPollerId($poller['id']);

        $this->generate($poller['id']);
    }

    /**
     * @param $cfg_path
     *
     * @return void
     */
    public function addCfgPath($cfg_path): void
    {
        $this->add_cfg_files[] = $cfg_path;
    }

    /**
     * @return void
     */
    public function reset(): void
    {
        $this->add_cfg_files = [];
    }
}
