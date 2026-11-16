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

use Core\Metric\Domain\TimeSeries\StatusTimeline;
use Core\Metric\Infrastructure\Graph\LegacyMetricsDataSource;

/**
 * Class
 *
 * @class CentreonGraphStatus
 * @description Class for get status for a service and return this on JSON
 */
class CentreonGraphStatus
{
    /** @var CentreonDB */
    public $pearDB;

    /** @var CentreonDB */
    public $pearDBMonitoring;

    /** @var int */
    public $index;

    /** @var int */
    public $startTime;

    /** @var int */
    public $endTime;

    /** @var string */
    public $statusPath;

    /** @var array */
    public $generalOpt;

    /** @var array */
    public $rrdCachedOptions;

    /** @var */
    public $arguments;

    /** @var */
    public $rrdOptions;

    /**
     * CentreonGraphStatus Constructor
     *
     * @param int $index The index data id
     * @param int $start The start time
     * @param int $end The end time
     *
     * @throws RuntimeException
     */
    public function __construct($index, $start, $end)
    {
        $this->pearDB = new CentreonDB();
        $this->pearDBMonitoring = new CentreonDB('centstorage');
        $this->index = $index;
        $this->startTime = $start;
        $this->endTime = $end;
        $this->statusPath = $this->getStatusPath();
        $this->generalOpt = $this->getOptions();
        $this->rrdCachedOptions = $this->getRrdCachedOptions();
    }

    /**
     * Get the metrics
     *
     * @throws RuntimeException
     * @return array|array[]
     */
    public function getData()
    {
        if (LegacyMetricsDataSource::isVictoriaMetricsOption($this->generalOpt['metrics_data_source'] ?? null)) {
            return StatusTimeline::fromRows(
                LegacyMetricsDataSource::getGraphReader()->findStatusRows(
                    (int) $this->index,
                    (int) $this->startTime,
                    (int) $this->endTime
                )
            );
        }

        $this->setRRDOption('imgformat', 'JSONTIME');
        $this->setRRDOption('start', $this->startTime);
        $this->setRRDOption('end', $this->endTime);

        LegacyMetricsDataSource::checkRrdtool($this->generalOpt['rrdtool_path_bin'] ?? null);
        $path = $this->statusPath . '/' . $this->index . '.rrd';
        if (file_exists($path) === false) {
            throw new RuntimeException();
        }

        $this->addArgument('DEF:v1=' . $path . ':value:AVERAGE');
        $this->addArgument('VDEF:v1Average=v1,AVERAGE');
        $this->addArgument('LINE1:v1#0000ff:v1');

        $jsonData = $this->getJsonStream();

        return StatusTimeline::fromRows($jsonData['data'] ?? []);
    }

    /**
     * Flush status rrdfile from cache
     *
     * @param int $indexData The index data id
     *
     * @return bool
     */
    public function flushRrdCached($indexData)
    {
        if (! isset($this->rrdCachedOptions['rrd_cached_option'])
            || ! in_array($this->rrdCachedOptions['rrd_cached_option'], ['unix', 'tcp'])
        ) {
            return true;
        }

        $errno = 0;
        $errstr = '';
        if ($this->rrdCachedOptions['rrd_cached_option'] === 'tcp') {
            $sock = fsockopen('127.0.0.1', trim($this->rrdCachedOptions['rrd_cached']), $errno, $errstr);
        } elseif ($this->rrdCachedOptions['rrd_cached_option'] === 'unix') {
            $sock = fsockopen('unix://' . trim($this->rrdCachedOptions['rrd_cached']), $errno, $errstr);
        } else {
            return false;
        }

        if ($sock === false) {
            return false;
        }

        if (fputs($sock, "BATCH\n") === false) {
            @fclose($sock);

            return false;
        }
        if (fgets($sock) === false) {
            @fclose($sock);

            return false;
        }

        $fullpath = realpath($this->statusPath . $indexData . '.rrd');
        $cmd = 'FLUSH ' . $fullpath;
        if (fputs($sock, $cmd . "\n") === false) {
            @fclose($sock);

            return false;
        }

        if (fputs($sock, ".\n") === false) {
            @fclose($sock);

            return false;
        }
        if (fgets($sock) === false) {
            @fclose($sock);

            return false;
        }

        fputs($sock, "QUIT\n");
        @fclose($sock);

        return true;
    }

    /**
     * Get the index data id for a service
     *
     * @param int $hostId The host id
     * @param int $serviceId The service id
     * @param CentreonDB $dbc The database connection to centreon_storage
     *
     * @throws OutOfRangeException
     * @throws PDOException
     * @return int
     */
    public static function getIndexId($hostId, $serviceId, $dbc)
    {
        $query = 'SELECT id, 1 AS REALTIME FROM index_data WHERE host_id = :hostId AND service_id = :serviceId';
        $res = $dbc->prepare($query);
        $res->bindValue(':hostId', (int) $hostId, PDO::PARAM_INT);
        $res->bindValue(':serviceId', (int) $serviceId, PDO::PARAM_INT);
        $res->execute();
        $row = $res->fetch();

        if ($row == false) {
            throw new OutOfRangeException();
        }

        return $row['id'];
    }

    /**
     * Get general options
     *
     * @throws RuntimeException
     * @return array The list of genreal options
     */
    protected function getOptions()
    {
        $result = [];
        $query = "SELECT `key`, `value` FROM options
            WHERE `key` IN ('rrdtool_path_bin', 'rrdcached_enabled', 'debug_rrdtool', 'debug_path', 'metrics_data_source')";
        try {
            $res = $this->pearDB->query($query);
        } catch (PDOException $e) {
            throw new RuntimeException();
        }
        while ($row = $res->fetch()) {
            $result[$row['key']] = $row['value'];
        }

        return $result;
    }

    /**
     * Get the RRDCacheD options of local RRD Broker
     *
     * @throws PDOException
     * @return array of RRDCacheD options
     */
    protected function getRrdCachedOptions()
    {
        $result = $this->pearDB->query(
            "SELECT config_key, config_value
            FROM cfg_centreonbroker_info AS cbi
            INNER JOIN cfg_centreonbroker AS cb ON (cb.config_id = cbi.config_id)
            INNER JOIN nagios_server AS ns ON (ns.id = cb.ns_nagios_server)
            WHERE ns.localhost = '1'
            AND cbi.config_key IN ('rrd_cached_option', 'rrd_cached')"
        );

        $rrdCachedOptions = [];
        while ($row = $result->fetch()) {
            $this->rrdCachedOptions[$row['config_key']] = $row['config_value'];
        }

        return $rrdCachedOptions;
    }

    /**
     * Get the status RRD path
     *
     * @throws PDOException
     * @throws RuntimeException
     * @return string The status RRD path
     */
    protected function getStatusPath()
    {
        $query = 'SELECT RRDdatabase_status_path FROM config';
        $res = $this->pearDBMonitoring->query($query);
        $row = $res->fetch();
        if ($row === null) {
            throw new RuntimeException('Missing status directory configuration');
        }

        return $row['RRDdatabase_status_path'];
    }

    /**
     * Add argument rrdtool
     *
     * @param string $arg
     *
     * @return void
     */
    protected function addArgument($arg)
    {
        $this->arguments[] = $arg;
    }

    /**
     * Add argument rrdtool
     *
     * @param string $name the key
     * @param string $value
     *
     * @return void
     */
    protected function setRRDOption($name, $value = null)
    {
        if (str_contains($value, ' ')) {
            $value = "'" . $value . "'";
        }
        $this->rrdOptions[$name] = $value;
    }

    /**
     * Log message
     *
     * @param string $message
     *
     * @return void
     */
    private function log($message): void
    {
        if ($this->generalOpt['debug_rrdtool']
            && is_writable($this->generalOpt['debug_path'])) {
            error_log(
                '[' . date('d/m/Y H:i') . '] RDDTOOL : ' . $message . " \n",
                3,
                $this->generalOpt['debug_path'] . 'rrdtool.log'
            );
        }
    }

    /**
     * Get rrdtool result
     *
     * @return mixed
     */
    private function getJsonStream()
    {
        $rrdtool = LegacyMetricsDataSource::checkRrdtool($this->generalOpt['rrdtool_path_bin'] ?? null);
        $rrdData = null;
        $this->flushRrdcached($this->index);

        $commandLine = '';
        $commandLine = ' graph - ';

        foreach ($this->rrdOptions as $key => $value) {
            $commandLine .= '--' . $key;
            if (isset($value)) {
                if (preg_match('/\'/', $value)) {
                    $value = "'" . preg_replace('/\'/', ' ', $value) . "'";
                }
                $commandLine .= '=' . $value;
            }
            $commandLine .= ' ';
        }

        foreach ($this->arguments as $arg) {
            $commandLine .= ' ' . $arg . ' ';
        }
        $commandLine = preg_replace('/(\\$|`)/', '', $commandLine);
        $this->log($commandLine);

        if (is_writable($this->generalOpt['debug_path'])) {
            $stderr = ['file', $this->generalOpt['debug_path'] . '/rrdtool.log', 'a'];
        } else {
            $stderr = ['pipe', 'a'];
        }
        $descriptorspec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => $stderr];

        $process = proc_open($rrdtool . ' - ', $descriptorspec, $pipes, null, null);

        if (is_resource($process)) {
            fwrite($pipes[0], $commandLine);
            fclose($pipes[0]);

            $str = stream_get_contents($pipes[1]);
            $returnValue = proc_close($process);

            $str = preg_replace('/OK u:.*$/', '', $str);
            $rrdData = json_decode($str, true);
        }

        return $rrdData;
    }
}
