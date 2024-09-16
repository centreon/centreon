<?php

/*
 * Copyright 2005-2015 Centreon
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

/**
 * Class
 *
 * @class Broker
 */
class Broker extends AbstractObjectJSON
{
    private const STREAM_BBDO_SERVER = 'bbdo_server';
    private const STREAM_BBDO_CLIENT = 'bbdo_client';

    /** @var null */
    protected $engine = null;
    /** @var null */
    protected $broker = null;
    /** @var null */
    protected $generate_filename = null;
    /** @var null */
    protected $object_name = null;
    /** @var string */
    protected $attributes_select = '
        config_id,
        config_name,
        config_filename,
        config_write_timestamp,
        config_write_thread_id,
        ns_nagios_server,
        event_queue_max_size,
        event_queues_total_size,
        command_file,
        cache_directory,
        stats_activate,
        daemon,
        log_directory,
        log_filename,
        log_max_size,
        pool_size,
        bbdo_version
    ';
    /** @var string */
    protected $attributes_select_parameters = '
        config_group,
        config_group_id,
        config_id,
        config_key,
        config_value,
        grp_level,
        subgrp_id,
        parent_grp_id,
        fieldIndex
    ';
    /** @var string */
    protected $attributes_engine_parameters = '
        id,
        name,
        centreonbroker_module_path,
        centreonbroker_cfg_path,
        centreonbroker_logs_path
    ';
    /** @var string[] */
    protected $exclude_parameters = ['blockId'];
    /** @var string[] */
    protected $authorized_empty_field = ['db_password'];
    /** @var null */
    protected $stmt_engine = null;
    /** @var null */
    protected $stmt_broker = null;
    /** @var null */
    protected $stmt_broker_parameters = null;
    /** @var null */
    protected $stmt_engine_parameters = null;
    /** @var null */
    protected $cacheExternalValue = null;
    /** @var null */
    protected $cacheLogValue = null;
    /** @var object|null */
    protected $readVaultConfigurationRepository = null;

    /**
     * Broker constructor
     *
     * @param \Pimple\Container $dependencyInjector
     *
     * @throws LogicException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     */
    public function __construct(\Pimple\Container $dependencyInjector)
    {
        parent::__construct($dependencyInjector);

        // Get Centeron Vault Storage configuration
        $kernel = \App\Kernel::createForWeb();
        $this->readVaultConfigurationRepository = $kernel->getContainer()->get(
            Core\Security\Vault\Application\Repository\ReadVaultConfigurationRepositoryInterface::class
        );
    }

    /**
     * @return void
     * @throws PDOException
     */
    private function getExternalValues(): void
    {
        if (!is_null($this->cacheExternalValue)) {
            return;
        }

        $this->cacheExternalValue = [];
        $stmt = $this->backend_instance->db->prepare("
            SELECT CONCAT(cf.fieldname, '_', cttr.cb_tag_id, '_', ctfr.cb_type_id) as name, external
            FROM cb_field cf, cb_type_field_relation ctfr, cb_tag_type_relation cttr
            WHERE cf.external IS NOT NULL
            AND cf.cb_field_id = ctfr.cb_field_id
            AND ctfr.cb_type_id = cttr.cb_type_id
        ");
        $stmt->execute();
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
            $this->cacheExternalValue[$row['name']] = $row['external'];
        }
    }

    /**
     * @return void
     * @throws PDOException
     */
    private function getLogsValues(): void
    {
        if (!is_null($this->cacheLogValue)) {
            return;
        }
        $this->cacheLogValue = [];
        $stmt = $this->backend_instance->db->prepare("
            SELECT relation.`id_centreonbroker`, log.`name`, lvl.`name` as level
            FROM `cfg_centreonbroker_log` relation
            INNER JOIN `cb_log` log
                ON relation.`id_log` = log.`id`
            INNER JOIN `cb_log_level` lvl
                ON relation.`id_level` = lvl.`id`
        ");
        $stmt->execute();
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
            $this->cacheLogValue[$row['id_centreonbroker']][$row['name']] = $row['level'];
        }
    }

    /**
     * @param $poller_id
     * @param $localhost
     *
     * @return void
     * @throws PDOException
     * @throws RuntimeException
     */
    private function generate($poller_id, $localhost): void
    {
        $this->getExternalValues();

        if (is_null($this->stmt_broker)) {
            $this->stmt_broker = $this->backend_instance->db->prepare("
            SELECT $this->attributes_select
            FROM cfg_centreonbroker
            WHERE ns_nagios_server = :poller_id
            AND config_activate = '1'
            ");
        }
        $this->stmt_broker->bindParam(':poller_id', $poller_id, PDO::PARAM_INT);
        $this->stmt_broker->execute();

        $this->getEngineParameters($poller_id);

        if (is_null($this->stmt_broker_parameters)) {
            $this->stmt_broker_parameters = $this->backend_instance->db->prepare("SELECT
              $this->attributes_select_parameters
            FROM cfg_centreonbroker_info
            WHERE config_id = :config_id
            ORDER BY config_group, config_group_id
            ");
        }

        $watchdog = [];
        $anomalyDetectionLuaOutputGroupID = -1;

        $result = $this->stmt_broker->fetchAll(PDO::FETCH_ASSOC);
        foreach ($result as $row) {
            $this->generate_filename = $row['config_filename'];
            $object = [];

            $config_name = $row['config_name'];
            $cache_directory = $row['cache_directory'];
            $stats_activate = $row['stats_activate'];

            // Base parameters
            $object['broker_id'] = (int) $row['config_id'];
            $object['broker_name'] = $row['config_name'];
            $object['poller_id'] = (int) $this->engine['id'];
            $object['poller_name'] = $this->engine['name'];
            $object['module_directory'] = (string) $this->engine['broker_modules_path'];
            $object['log_timestamp'] = filter_var($row['config_write_timestamp'], FILTER_VALIDATE_BOOLEAN);
            $object['log_thread_id'] = filter_var($row['config_write_thread_id'], FILTER_VALIDATE_BOOLEAN);
            $object['event_queue_max_size'] = (int)$row['event_queue_max_size'];
            if (! empty($row['event_queues_total_size'])) {
                $object['event_queues_total_size'] = (int)$row['event_queues_total_size'];
            }
            $object['command_file'] = (string) $row['command_file'];
            $object['cache_directory'] = (string) $cache_directory;
            $object['bbdo_version'] = (string) $row['bbdo_version'];
            if (!empty($row['pool_size'])) {
                $object['pool_size'] = (int)$row['pool_size'];
            }

            if ($row['daemon'] == '1') {
                $watchdog['cbd'][] = [
                    'name' => $row['config_name'],
                    'configuration_file' => $this->engine['broker_cfg_path'] . '/' . $row['config_filename'],
                    'run' => true,
                    'reload' => true,
                ];
            }

            $this->stmt_broker_parameters->bindParam(':config_id', $row['config_id'], PDO::PARAM_INT);
            $this->stmt_broker_parameters->execute();
            $resultParameters = $this->stmt_broker_parameters->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

            //logger
            $object['log']['directory'] = \HtmlAnalyzer::sanitizeAndRemoveTags($row['log_directory']);
            $object['log']['filename'] = \HtmlAnalyzer::sanitizeAndRemoveTags($row['log_filename']);
            $object['log']['max_size'] = filter_var($row['log_max_size'], FILTER_VALIDATE_INT);
            $this->getLogsValues();
            $logs = $this->cacheLogValue[$object['broker_id']];
            $object['log']['loggers'] = $logs;

            $reindexedObjectKeys = [];

            // Flow parameters
            foreach ($resultParameters as $key => $value) {
                // We search the BlockId
                $blockId = 0;
                for ($i = count($value); $i > 0; $i--) {
                    if (isset($value[$i]['config_key']) && $value[$i]['config_key'] == 'blockId') {
                        $blockId = $value[$i]['config_value'];
                        $configGroupId = $value[$i]['config_group_id'];
                        break;
                    }
                }

                $subValuesToCastInArray = [];
                $rrdCacheOption = null;
                $rrdCached = null;
                foreach ($value as $subvalue) {
                    if (
                        !isset($subvalue['fieldIndex'])
                        || $subvalue['fieldIndex'] == ""
                        || is_null($subvalue['fieldIndex'])
                    ) {
                        if (in_array($subvalue['config_key'], $this->exclude_parameters)) {
                            continue;
                        } elseif (trim($subvalue['config_value']) == '' &&
                            !in_array(
                                $subvalue['config_key'],
                                $this->authorized_empty_field
                            )
                        ) {
                            continue;
                        } elseif ($subvalue['config_key'] === 'category') {
                            $object[$key][$subvalue['config_group_id']]['filters'][$subvalue['config_key']][] =
                                $subvalue['config_value'];
                        } elseif (in_array($subvalue['config_key'], ['rrd_cached_option', 'rrd_cached'])) {
                            if ($subvalue['config_key'] === 'rrd_cached_option') {
                                $rrdCacheOption = $subvalue['config_value'];
                            } elseif ($subvalue['config_key'] === 'rrd_cached') {
                                $rrdCached = $subvalue['config_value'];
                            }
                            if ($rrdCached && $rrdCacheOption) {
                                if ($rrdCacheOption === 'tcp') {
                                    $object[$key][$subvalue['config_group_id']]['port'] = $rrdCached;
                                } elseif ($rrdCacheOption === 'unix') {
                                    $object[$key][$subvalue['config_group_id']]['path'] = $rrdCached;
                                }
                            }
                        } else {
                            $object[$key][$subvalue['config_group_id']][$subvalue['config_key']] =
                                $subvalue['config_value'];

                            // We override with external values
                            if (isset($this->cacheExternalValue[$subvalue['config_key'] . '_' . $blockId])) {
                                $object[$key][$subvalue['config_group_id']][$subvalue['config_key']] =
                                    $this->getInfoDb(
                                        $this->cacheExternalValue[$subvalue['config_key'] . '_' . $blockId]
                                    );
                            }
                            // Let broker insert in index data in pollers
                            if (
                                $subvalue['config_key'] === 'type'
                                && $subvalue['config_value'] === 'storage'
                                && !$localhost
                            ) {
                                $object[$key][$subvalue['config_group_id']]['insert_in_index_data'] = 'yes';
                            }
                        }
                    } else {
                        $res = explode('__', $subvalue['config_key'], 3);
                        $object[$key][$subvalue['config_group_id']][$res[0]][(int)$subvalue['fieldIndex']][$res[1]] =
                            $subvalue['config_value'];
                        $subValuesToCastInArray[$subvalue['config_group_id']][] = $res[0];

                        if ((strcmp(
                                $object[$key][$subvalue['config_group_id']]['name'],
                                "forward-to-anomaly-detection"
                            ) == 0)
                            && (strcmp(
                                $object[$key][$subvalue['config_group_id']]['path'],
                                "/usr/share/centreon-broker/lua/centreon-anomaly-detection.lua"
                            ) == 0)
                        ) {
                            $anomalyDetectionLuaOutputGroupID = $subvalue['config_group_id'];
                        }
                    }
                }

                // Check if we need to add values from external
                foreach ($this->cacheExternalValue as $key2 => $value2) {
                    if (preg_match('/^(.+)_' . $blockId . '$/', $key2, $matches)) {
                        if (!isset($object[$configGroupId][$key][$matches[1]])) {
                            $object[$key][$configGroupId][$matches[1]] =
                                $this->getInfoDb($value2);
                        }
                    }
                }

                // cast into arrays instead of objects with integer as key
                foreach ($subValuesToCastInArray as $configGroupId => $subValues) {
                    foreach ($subValues as $subValue) {
                        $object[$key][$configGroupId][$subValue] =
                            array_values($object[$key][$configGroupId][$subValue]);
                    }
                }

                $reindexedObjectKeys[] = $key;
            }

            // Stats parameters
            if ($stats_activate == '1') {
                $object['stats'] = [
                    [
                        'type' => 'stats',
                        'name' => $config_name . '-stats',
                        'json_fifo' => $cache_directory . '/' . $config_name . '-stats.json',
                    ],
                ];
            }

            if ($anomalyDetectionLuaOutputGroupID >= 0) {
                $luaParameters = $this->generateAnomalyDetectionLuaParameters();
                if ($luaParameters !== []) {
                    $object["output"][$anomalyDetectionLuaOutputGroupID]['lua_parameter'] = array_merge_recursive(
                        $object["output"][$anomalyDetectionLuaOutputGroupID]['lua_parameter'],
                        $luaParameters
                    );
                }
                $anomalyDetectionLuaOutputGroupID = -1;
            }

            // gRPC parameters
            $object['grpc'] = [
                'port' => 51000 + (int) $row['config_id']
            ];

            // Force as list-array eventually wrong indexed object keys (input, output)
            foreach ($reindexedObjectKeys as $key) {
                if (is_array($object[$key])) {
                    $object[$key] = array_values($object[$key]);
                }
            }

            // Remove unnecessary element form inputs and output for stream types bbdo
            $object = $this->cleanBbdoStreams($object);

            // Add vault path if vault if defined
            if ($this->readVaultConfigurationRepository->exists()) {
                $object['vault_configuration'] = $this->readVaultConfigurationRepository->getLocation();
            }

            // Generate file
            $this->generateFile($object);
            $this->writeFile($this->backend_instance->getPath());
        }

        // Manage path of cbd watchdog log
        $watchdogLogsPath = $this->engine['broker_logs_path'] === null || empty(trim($this->engine['broker_logs_path']))
            ? '/var/log/centreon-broker/watchdog.log'
            : trim($this->engine['broker_logs_path']) . '/watchdog.log';
        $watchdog['log'] = $watchdogLogsPath;

        $this->generate_filename = 'watchdog.json';
        $this->generateFile($watchdog);
        $this->writeFile($this->backend_instance->getPath());
    }

    /**
     * Remove unnecessary element form inputs and output for stream types bbdo
     *
     * @param array<string,mixed> $config
     * @return array<string,mixed>
     */
    private function cleanBbdoStreams(array $config): array
    {
        if (isset($config['input'])) {
            foreach ($config['input'] as $key => $inputCfg) {
                if ($inputCfg['type'] === self::STREAM_BBDO_SERVER) {
                    unset($config['input'][$key]['compression']);
                    unset($config['input'][$key]['retention']);

                    if ($config['input'][$key]['encryption'] === 'no') {
                        unset($config['input'][$key]['private_key']);
                        unset($config['input'][$key]['certificate']);
                    }
                }
                if ($inputCfg['type'] === self::STREAM_BBDO_CLIENT) {
                    unset($config['input'][$key]['compression']);

                    if ($config['input'][$key]['encryption'] === 'no') {
                        unset($config['input'][$key]['ca_certificate']);
                        unset($config['input'][$key]['ca_name']);
                    }
                }
            }
        }
        if (isset($config['output'])) {
            foreach ($config['output'] as $key => $inputCfg) {
                if ($inputCfg['type'] === self::STREAM_BBDO_SERVER && $config['output'][$key]['encrypt'] === 'no') {
                    unset($config['output'][$key]['private_key']);
                    unset($config['output'][$key]['certificate']);
                }
                if ($inputCfg['type'] === self::STREAM_BBDO_CLIENT && $config['output'][$key]['encrypt'] === 'no') {
                    unset($config['output'][$key]['ca_certificate']);
                    unset($config['output'][$key]['ca_name']);
                }
            }
        }

        return $config;
    }

    /**
     * @param $poller_id
     *
     * @return void
     * @throws PDOException
     */
    private function getEngineParameters($poller_id): void
    {
        if (is_null($this->stmt_engine_parameters)) {
            $this->stmt_engine_parameters = $this->backend_instance->db->prepare("SELECT
              $this->attributes_engine_parameters
            FROM nagios_server
            WHERE id = :poller_id
            ");
        }
        $this->stmt_engine_parameters->bindParam(':poller_id', $poller_id, PDO::PARAM_INT);
        $this->stmt_engine_parameters->execute();
        try {
            $row = $this->stmt_engine_parameters->fetch(PDO::FETCH_ASSOC);
            $this->engine['id'] = $row['id'];
            $this->engine['name'] = $row['name'];
            $this->engine['broker_modules_path'] = $row['centreonbroker_module_path'];
            $this->engine['broker_cfg_path'] = $row['centreonbroker_cfg_path'];
            $this->engine['broker_logs_path'] = $row['centreonbroker_logs_path'];
        } catch (Exception $e) {
            throw new Exception('Exception received : ' . $e->getMessage() . "\n");
        }
    }

    /**
     * @param $poller
     *
     * @return void
     * @throws PDOException
     * @throws RuntimeException
     */
    public function generateFromPoller($poller): void
    {
        $this->generate($poller['id'], $poller['localhost']);
    }

    /**
     * @param $string
     *
     * @return array|false|mixed|string
     * @throws PDOException
     */
    private function getInfoDb($string)
    {
        /*
         * Default values
         */
        $s_db = "centreon";
        $s_rpn = null;
        /*
         * Parse string
         */
        $configs = explode(':', $string);
        foreach ($configs as $config) {
            if (!str_contains($config, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $config);
            switch ($key) {
                case 'D':
                    $s_db = $value;
                    break;
                case 'T':
                    $s_table = $value;
                    break;
                case 'C':
                    $s_column = $value;
                    break;
                case 'F':
                    $s_filter = $value;
                    break;
                case 'K':
                    $s_key = $value;
                    break;
                case 'CK':
                    $s_column_key = $value;
                    break;
                case 'RPN':
                    $s_rpn = $value;
                    break;
            }
        }
        /*
         * Construct query
         */
        if (!isset($s_table) || !isset($s_column)) {
            return false;
        }
        $query = "SELECT `" . $s_column . "` FROM `" . $s_table . "`";
        if (isset($s_column_key) && isset($s_key)) {
            $query .= " WHERE `" . $s_column_key . "` = '" . $s_key . "'";
        }

        /*
         * Execute the query
         */
        switch ($s_db) {
            case 'centreon':
                $db = $this->backend_instance->db;
                break;
            case 'centreon_storage':
                $db = $this->backend_instance->db_cs;
                break;
        }

        $stmt = $db->prepare($query);
        $stmt->execute();

        $infos = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
            $val = $row[$s_column];
            if (!is_null($s_rpn)) {
                $val = (string) $this->rpnCalc($s_rpn, $val);
            }
            $infos[] = $val;
        }
        if (count($infos) == 0) {
            return "";
        } elseif (count($infos) == 1) {
            return $infos[0];
        }
        return $infos;
    }

    /**
     * @param $rpn
     * @param $val
     *
     * @return float|int|mixed|string
     */
    private function rpnCalc($rpn, $val)
    {
        if (!is_numeric($val)) {
            return $val;
        }
        try {
            $val = array_reduce(
                preg_split('/\s+/', $val . ' ' . $rpn),
                [$this, 'rpnOperation']
            );
            return $val[0];
        } catch (InvalidArgumentException $e) {
            return $val;
        }
    }

    /**
     * @param $result
     * @param $item
     *
     * @return array|mixed
     * @throws InvalidArgumentException
     */
    private function rpnOperation($result, $item)
    {
        if (in_array($item, ['+', '-', '*', '/'])) {
            if (count($result) < 2) {
                throw new InvalidArgumentException('Not enough arguments to apply operator');
            }
            $a = $result[0];
            $b = $result[1];
            $result = [];
            $result[0] = eval("return $a $item $b;");
        } elseif (is_numeric($item)) {
            $result[] = $item;
        } else {
            throw new InvalidArgumentException('Unrecognized symbol ' . $item);
        }
        return $result;
    }

    /**
     * Method retrieving the Centreon Platform UUID generated during web installation
     *
     * @return string|null
     */
    private function getCentreonPlatformUuid(): ?string
    {
        global $pearDB;
        $result = $pearDB->query("SELECT `value` FROM informations WHERE `key` = 'uuid'");

        if (! $record = $result->fetch(\PDO::FETCH_ASSOC)) {
            return null;
        };

        return $record['value'];
    }

    /**
     * Generate complete proxy url.
     *
     * @throws PDOException
     *
     * @return array with lua parameters
     */
    private function generateAnomalyDetectionLuaParameters(): array
    {
        global $pearDB;

        $sql = <<<'SQL'
            SELECT
              `key`,
              `value`
            FROM
              `options`
            WHERE
              `key` IN (
                'saas_token', 'saas_use_proxy', 'saas_url',
                'proxy_url', 'proxy_port', 'proxy_user', 'proxy_password'
              )
            SQL;

        /**
         * @var array{
         *     saas_token?: null|string,
         *     saas_use_proxy?: null|string,
         *     saas_url?: null|string,
         *     proxy_url?: null|string,
         *     proxy_port?: null|string,
         *     proxy_user?: null|string,
         *     proxy_password?: null|string
         * } $options
         */
        $options = $pearDB->query($sql)->fetchAll(\PDO::FETCH_KEY_PAIR) ?: [];

        $luaParameters = [];

        if (array_key_exists('saas_token', $options)) {
            $luaParameters[] = [
                'type' => 'string',
                'name' => 'token',
                'value' => $options['saas_token'],
            ];
        }
        if (array_key_exists('saas_url', $options)) {
            $luaParameters[] = [
                'type' => 'string',
                'name' => 'destination',
                'value' => $options['saas_url'],
            ];
        }
        if (
            ($options['saas_use_proxy'] ?? null) === '1'
            && ($proxyUrl = $options['proxy_url'] ?? null)
        ) {
            $proxyScheme = parse_url($proxyUrl, PHP_URL_SCHEME);

            $proxy = ($proxyScheme ?: 'http') . '://';
            if (! empty($options['proxy_user']) && ! empty($options['proxy_password'])) {
                $proxy .= $options['proxy_user'] . ':' . $options['proxy_password'] . '@';
            }
            if ($proxyScheme) {
                $proxy .= parse_url($proxyUrl, PHP_URL_HOST);
            } else {
                $proxy .= parse_url($proxyUrl, PHP_URL_PATH);
            }
            if (! empty($options['proxy_port'])) {
                $proxy .= ':' . $options['proxy_port'];
            }
            $luaParameters[] = [
                'type' => 'string',
                'name' => 'proxy',
                'value' => $proxy,
            ];
        }

        $luaParameters[] = [
            'type' => 'string',
            'name' => 'centreon_platform_uuid',
            'value' => $this->getCentreonPlatformUuid(),
        ];

        return $luaParameters;
    }
}
