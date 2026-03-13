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

use App\Kernel;
use Core\Common\Application\UseCase\VaultTrait;
use Core\MonitoringServer\Application\Repository\ReadMonitoringServerRepositoryInterface;
use Pimple\Container;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * Class
 *
 * @class Broker
 */
class Broker extends AbstractObjectJSON
{
    use VaultTrait;
    private const STREAM_BBDO_SERVER = 'bbdo_server';
    private const STREAM_BBDO_CLIENT = 'bbdo_client';

    /** @var array|null */
    protected $engine = null;

    /** @var mixed|null */
    protected $broker = null;

    /** @var string|null */
    protected $generate_filename = null;

    /** @var string|null */
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

    /** @var CentreonDBStatement|null */
    protected $stmt_engine = null;

    /** @var CentreonDBStatement|null */
    protected $stmt_broker = null;

    /** @var CentreonDBStatement|null */
    protected $stmt_broker_parameters = null;

    /** @var CentreonDBStatement|null */
    protected $stmt_engine_parameters = null;

    /** @var array|null */
    protected $cacheExternalValue = null;

    /** @var array|null */
    protected $cacheLogValue = null;

    /** @var object|null */
    protected $readVaultConfigurationRepository = null;

    private ReadMonitoringServerRepositoryInterface $readMonitoringServerRepository;

    /**
     * Broker constructor
     *
     * @param Container $dependencyInjector
     *
     * @throws LogicException
     * @throws ServiceCircularReferenceException
     * @throws ServiceNotFoundException
     */
    public function __construct(Container $dependencyInjector)
    {
        parent::__construct($dependencyInjector);

        // Get Centeron Vault Storage configuration
        $kernel = Kernel::createForWeb();
        $this->readVaultConfigurationRepository = $kernel->getContainer()->get(
            Core\Security\Vault\Application\Repository\ReadVaultConfigurationRepositoryInterface::class
        );
        $this->readMonitoringServerRepository = $kernel->getContainer()->get(
            ReadMonitoringServerRepositoryInterface::class
        );
    }

    /**
     * @param $poller
     *
     * @throws PDOException
     * @throws RuntimeException
     * @return void
     */
    public function generateFromPoller($poller): void
    {
        $this->generate($poller['id'], $poller['localhost']);
    }

    /**
     * @throws PDOException
     * @return void
     */
    private function getExternalValues(): void
    {
        if (! is_null($this->cacheExternalValue)) {
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
     * @throws PDOException
     * @return void
     */
    private function getLogsValues(): void
    {
        if (! is_null($this->cacheLogValue)) {
            return;
        }
        $this->cacheLogValue = [];
        $stmt = $this->backend_instance->db->prepare('
            SELECT relation.`id_centreonbroker`, log.`name`, lvl.`name` as level
            FROM `cfg_centreonbroker_log` relation
            INNER JOIN `cb_log` log
                ON relation.`id_log` = log.`id`
            INNER JOIN `cb_log_level` lvl
                ON relation.`id_level` = lvl.`id`
        ');
        $stmt->execute();
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
            $this->cacheLogValue[$row['id_centreonbroker']][$row['name']] = $row['level'];
        }
    }

    /**
     * @param $poller_id
     * @param $localhost
     * @param mixed $pollerId
     *
     * @throws PDOException
     * @throws RuntimeException
     * @return void
     */
    private function generate($pollerId, $localhost): void
    {
        $this->getExternalValues();

        if (is_null($this->stmt_broker)) {
            $this->stmt_broker = $this->backend_instance->db->prepare("
            SELECT {$this->attributes_select}
            FROM cfg_centreonbroker
            WHERE ns_nagios_server = :poller_id
            AND config_activate = '1'
            ");
        }
        $this->stmt_broker->bindParam(':poller_id', $pollerId, PDO::PARAM_INT);
        $this->stmt_broker->execute();

        $this->getEngineParameters($pollerId);

        if (is_null($this->stmt_broker_parameters)) {
            $this->stmt_broker_parameters = $this->backend_instance->db->prepare('
                SELECT bio.id, bio.tag, bio.type_id, bio.type_name, bio.name, bio.parameters,
                       ct.cb_tag_id
                FROM cfg_broker_input_output bio
                LEFT JOIN cb_tag ct ON ct.tagname = bio.tag
                WHERE bio.config_id = :config_id
                ORDER BY bio.tag, bio.id
            ');
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
            $object['event_queue_max_size'] = (int) $row['event_queue_max_size'];
            if (! empty($row['event_queues_total_size'])) {
                $object['event_queues_total_size'] = (int) $row['event_queues_total_size'];
            }
            $object['command_file'] = (string) $row['command_file'];
            $object['cache_directory'] = (string) $cache_directory;
            $object['bbdo_version'] = (string) $row['bbdo_version'];
            if (! empty($row['pool_size'])) {
                $object['pool_size'] = (int) $row['pool_size'];
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
            $resultParameters = $this->stmt_broker_parameters->fetchAll(PDO::FETCH_ASSOC);

            // logger
            $object['log']['directory'] = HtmlAnalyzer::sanitizeAndRemoveTags($row['log_directory']);
            $object['log']['filename'] = HtmlAnalyzer::sanitizeAndRemoveTags($row['log_filename']);
            $object['log']['max_size'] = filter_var($row['log_max_size'], FILTER_VALIDATE_INT);
            $this->getLogsValues();
            $logs = $this->cacheLogValue[$object['broker_id']];
            $object['log']['loggers'] = $logs;

            // Flow parameters
            foreach ($resultParameters as $flowRow) {
                $tag     = $flowRow['tag'];
                $typeId  = $flowRow['type_id'];
                $blockId = $flowRow['cb_tag_id'] . '_' . $typeId;
                $params  = json_decode($flowRow['parameters'], true) ?? [];

                $flow = [
                    'name' => $flowRow['name'],
                    'type' => $flowRow['type_name'],
                ];

                // rrd_cached_option + rrd_cached → port or path
                $rrdCacheOption = $params['rrd_cached_option'] ?? null;
                $rrdCached      = $params['rrd_cached'] ?? null;
                if ($rrdCached && $rrdCacheOption) {
                    if ($rrdCacheOption === 'tcp') {
                        $flow['port'] = $rrdCached;
                    } elseif ($rrdCacheOption === 'unix') {
                        $flow['path'] = $rrdCached;
                    }
                }
                unset($params['rrd_cached_option'], $params['rrd_cached']);

                // filters_category → filters.category array
                if (isset($params['filters_category'])) {
                    $flow['filters']['category'] = $params['filters_category'];
                    unset($params['filters_category']);
                }

                // Remaining parameters
                foreach ($params as $key => $value) {
                    if (is_array($value)) {
                        $flow[$key] = $value;
                        continue;
                    }
                    if (trim((string) $value) === '' && ! in_array($key, $this->authorized_empty_field)) {
                        continue;
                    }
                    if (isset($this->cacheExternalValue[$key . '_' . $blockId])) {
                        $value = $this->getInfoDb($this->cacheExternalValue[$key . '_' . $blockId]);
                    }
                    $flow[$key] = $value;
                }

                // Let broker insert in index data in pollers
                if ($flowRow['type_name'] === 'storage' && ! $localhost) {
                    $flow['insert_in_index_data'] = 'yes';
                }

                // External values for any missing field
                foreach ($this->cacheExternalValue as $extKey => $extValue) {
                    if (preg_match('/^(.+)_' . preg_quote($blockId, '/') . '$/', $extKey, $m)) {
                        if (! isset($flow[$m[1]])) {
                            $flow[$m[1]] = $this->getInfoDb($extValue);
                        }
                    }
                }

                // Track anomaly detection lua output index
                if (
                    $tag === 'output'
                    && ($flow['name'] ?? '') === 'forward-to-anomaly-detection'
                    && ($flow['path'] ?? '') === '/usr/share/centreon-broker/lua/centreon-anomaly-detection.lua'
                ) {
                    $anomalyDetectionLuaOutputGroupID = count($object['output'] ?? []);
                }

                $object[$tag][] = $flow;
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
                    $object['output'][$anomalyDetectionLuaOutputGroupID]['lua_parameter'] = array_merge_recursive(
                        $object['output'][$anomalyDetectionLuaOutputGroupID]['lua_parameter'],
                        $luaParameters
                    );
                }
                $anomalyDetectionLuaOutputGroupID = -1;
            }

            // gRPC parameters
            $object['grpc'] = [
                'port' => 51000 + (int) $row['config_id'],
            ];

            // Remove unnecessary element form inputs and output for stream types bbdo
            $object = $this->cleanBbdoStreams($object);

            // Add vault path if vault if defined
            if ($this->readVaultConfigurationRepository->exists()) {
                $object['vault_configuration'] = $this->readVaultConfigurationRepository->getLocation();
            }

            if ($this->isVaultEnabled && $this->readVaultRepository !== null) {
                foreach ($object['output'] as $outputIndex => $output) {
                    $this->processVaultOutput($output, $outputIndex, $object);
                }
            }

            $shouldBeEncrypted = $this->readMonitoringServerRepository->isEncryptionReady($pollerId);
            foreach ($object['output'] as &$output) {
                if (
                    ($output['type'] === 'sql' || $output['type'] === 'storage')
                    && array_key_exists('db_password', $output)
                ) {
                    $output['db_password'] = $shouldBeEncrypted
                        ? 'encrypt::' . $this->engineContextEncryption->crypt($output['db_password'])
                        : $output['db_password'];
                }
                if (! isset($output['lua_parameter']) || ! is_array($output['lua_parameter'])) {
                    continue;
                }

                foreach ($output['lua_parameter'] as &$luaParameter) {
                    if (
                        isset($luaParameter['type'], $luaParameter['value'])
                        && $luaParameter['type'] === 'password'
                        && is_string($luaParameter['value'])
                    ) {
                        $luaParameter['value'] = $shouldBeEncrypted
                        ? 'encrypt::' . $this->engineContextEncryption->crypt($luaParameter['value'])
                        : $luaParameter['value'];
                    }
                }
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
                    unset($config['input'][$key]['compression'], $config['input'][$key]['retention']);

                    if ($config['input'][$key]['encryption'] === 'no') {
                        unset($config['input'][$key]['private_key'], $config['input'][$key]['certificate']);

                    }
                }
                if ($inputCfg['type'] === self::STREAM_BBDO_CLIENT) {
                    unset($config['input'][$key]['compression']);

                    if ($config['input'][$key]['encryption'] === 'no') {
                        unset($config['input'][$key]['ca_certificate'], $config['input'][$key]['ca_name']);

                    }
                }
            }
        }
        if (isset($config['output'])) {
            foreach ($config['output'] as $key => $inputCfg) {
                if ($inputCfg['type'] === self::STREAM_BBDO_SERVER && $config['output'][$key]['encrypt'] === 'no') {
                    unset($config['output'][$key]['private_key'], $config['output'][$key]['certificate']);

                }
                if ($inputCfg['type'] === self::STREAM_BBDO_CLIENT && $config['output'][$key]['encrypt'] === 'no') {
                    unset($config['output'][$key]['ca_certificate'], $config['output'][$key]['ca_name']);

                }
            }
        }

        return $config;
    }

    /**
     * @param $poller_id
     *
     * @throws PDOException
     * @return void
     */
    private function getEngineParameters($poller_id): void
    {
        if (is_null($this->stmt_engine_parameters)) {
            $this->stmt_engine_parameters = $this->backend_instance->db->prepare("SELECT
              {$this->attributes_engine_parameters}
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
     * @param $string
     *
     * @throws PDOException
     * @return array|false|mixed|string
     */
    private function getInfoDb($string)
    {
        // Default values
        $s_db = 'centreon';
        $s_rpn = null;
        // Parse string
        $configs = explode(':', $string);
        foreach ($configs as $config) {
            if (! str_contains($config, '=')) {
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
        // Construct query
        if (! isset($s_table) || ! isset($s_column)) {
            return false;
        }
        $query = 'SELECT `' . $s_column . '` FROM `' . $s_table . '`';
        if (isset($s_column_key, $s_key)) {
            $query .= ' WHERE `' . $s_column_key . "` = '" . $s_key . "'";
        }

        // Execute the query
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
            if (! is_null($s_rpn)) {
                $val = (string) $this->rpnCalc($s_rpn, $val);
            }
            $infos[] = $val;
        }
        if (count($infos) == 0) {
            return '';
        }
        if (count($infos) == 1) {
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
        if (! is_numeric($val)) {
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
     * @throws InvalidArgumentException
     * @return array|mixed
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
            $result[0] = eval("return {$a} {$item} {$b};");
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

        if (! $record = $result->fetch(PDO::FETCH_ASSOC)) {
            return null;
        }

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
        $options = $pearDB->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

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

    /**
     * Process vault output and update object with vault data.
     *
     * @param array $output
     * @param int $outputIndex
     * @param array $object
     * @return void
     */
    private function processVaultOutput(array &$output, int $outputIndex, array &$object): void
    {
        foreach ($output as $outputKey => $outputValue) {
            if (is_string($outputValue) && $this->isAVaultPath($outputValue)) {
                $this->updateVaultData($output, $outputKey, $outputValue, $object['output'][$outputIndex]);
            }

            if ($outputKey === 'lua_parameter' && is_array($outputValue)) {
                $this->processLuaParameters($output, $outputKey, $outputValue, $object['output'][$outputIndex]);
            }
        }
    }

    /**
     * Update vault data for a given key.
     *
     * @param array $output
     * @param string $outputKey
     * @param string $outputValue
     * @param array $outputReference
     * @return void
     */
    private function updateVaultData(
        array &$output,
        string $outputKey,
        string $outputValue,
        array &$outputReference,
    ): void {
        $vaultData = $this->readVaultRepository->findFromPath($outputValue);
        $vaultKey = $output['name'] . '_' . $outputKey;
        if (array_key_exists($vaultKey, $vaultData)) {
            $outputReference[$outputKey] = $vaultData[$vaultKey];
        }
    }

    /**
     * Process Lua parameters and update with vault data if applicable.
     *
     * @param array $output
     * @param string $outputKey
     * @param array $luaParameters
     * @param array $outputReference
     * @return void
     */
    private function processLuaParameters(
        array &$output,
        string $outputKey,
        array $luaParameters,
        array &$outputReference,
    ): void {
        foreach ($luaParameters as $parameterIndex => $luaParameter) {
            if ($luaParameter['type'] === 'password'
                && is_string($luaParameter['value'])
                && $this->isAVaultPath($luaParameter['value'])
            ) {
                $vaultData = $this->readVaultRepository->findFromPath($luaParameter['value']);
                $vaultKey = $output['name'] . '_' . $outputKey . '_' . $luaParameter['name'];
                if (array_key_exists($vaultKey, $vaultData)) {
                    $outputReference[$outputKey][$parameterIndex]['value'] = $vaultData[$vaultKey];
                }
            }
        }
    }
}
