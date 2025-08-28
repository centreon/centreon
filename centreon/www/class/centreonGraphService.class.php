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

require_once 'centreonGraph.class.php';

/**
 * Class
 *
 * @class CentreonGraphService
 * @description Class for get metrics for a service and return this on JSON
 */
class CentreonGraphService extends CentreonGraph
{
    public $listMetricsId;

    protected $legends = [];

    /**
     * CentreonGraphService Constructor
     *
     * @param int $index The index data id
     * @param string $userId The session id
     *
     * @throws PDOException
     */
    public function __construct($index, $userId)
    {
        parent::__construct($userId, $index, 0, 1);
    }

    /**
     * Get the metrics
     *
     * @param int $rows The number of points returned (Default: 200)
     *
     * @throws RuntimeException
     * @return array
     */
    public function getData($rows = 200)
    {
        $legendDataInfo = ['last' => 'LAST', 'min' => 'MINIMUM', 'max' => 'MAXIMUM', 'average' => 'AVERAGE', 'total' => 'TOTAL'];

        // Flush RRDCached for have the last values
        $this->flushRrdCached($this->listMetricsId);

        $commandLine = '';
        $defType = [0 => 'CDEF', 1 => 'VDEF'];

        // Build command line
        $commandLine .= ' xport ';
        $commandLine .= ' --showtime';
        $commandLine .= ' --start ' . $this->RRDoptions['start'];
        $commandLine .= ' --end ' . $this->RRDoptions['end'];
        $commandLine .= ' --maxrows ' . $rows;

        // Build legend command line
        $extraLegend = false;
        $commandLegendLine = ' graph x';
        $commandLegendLine .= ' --start ' . $this->RRDoptions['start'];
        $commandLegendLine .= ' --end ' . $this->RRDoptions['end'];

        $metrics = [];
        $vname = [];
        $virtuals = [];
        $i = 0;

        // Parse metrics
        foreach ($this->metrics as $metric) {
            if (isset($metric['virtual']) && $metric['virtual'] == 1) {
                $virtuals[] = $metric;
                $vname[$metric['metric']] = 'vv' . $i;
            } else {
                $path = $this->dbPath . '/' . $metric['metric_id'] . '.rrd';
                if (false === file_exists($path)) {
                    throw new RuntimeException();
                }
                $commandLine .= ' DEF:v' . $i . '=' . $path . ':value:AVERAGE';
                $commandLegendLine .= ' DEF:v' . $i . '=' . $path . ':value:AVERAGE';
                $commandLine .= ' XPORT:v' . $i . ':v' . $i;
                $vname[$metric['metric']] = 'v' . $i;
                $info = ['data' => [], 'graph_type' => 'line', 'unit' => $metric['unit'], 'color' => $metric['ds_color_line'], 'negative' => false, 'stack' => false, 'crit' => null, 'warn' => null];
                $info['legend'] = str_replace('\\\\', '\\', $metric['metric_legend']);
                $info['metric_name'] = ! empty($metric['ds_name']) ? $metric['ds_name'] : $info['legend'];

                // Add legend getting data
                foreach ($legendDataInfo as $name => $key) {
                    if ($metric['ds_' . $name] !== '') {
                        $extraLegend = true;
                        if (($name == 'min' || $name == 'max')
                            && (isset($metric['ds_minmax_int'])
                                && $metric['ds_minmax_int'])
                        ) {
                            $displayformat = '%7.0lf';
                        } else {
                            $displayformat = '%7.2lf';
                        }
                        $commandLegendLine .= ' VDEF:l' . $i . $key . '=v' . $i . ',' . $key;
                        $commandLegendLine .= ' PRINT:l' . $i . $key . ':"'
                            . str_replace(':', '\:', $metric['metric_legend'])
                            . '|' . ucfirst($name) . '|' . $displayformat . '"';
                    }
                }

                if (isset($metric['ds_color_area'], $metric['ds_filled'])
                    && $metric['ds_filled'] === '1'
                ) {
                    $info['graph_type'] = 'area';
                }
                if (isset($metric['ds_invert']) && $metric['ds_invert'] == 1) {
                    $info['negative'] = true;
                }
                if (isset($metric['stack'])) {
                    $info['stack'] = $metric['stack'] == 1;
                }
                if (isset($metric['crit'])) {
                    $info['crit'] = $metric['crit'];
                }
                if (isset($metric['warn'])) {
                    $info['warn'] = $metric['warn'];
                }
                $metrics[] = $info;
            }

            $i++;
        }
        // Append virtual metrics
        foreach ($virtuals as $metric) {
            $commandLine .= ' ' . $defType[$metric['def_type']] . ':'
                . $vname[$metric['metric']] . '='
                . $this->subsRPN($metric['rpn_function'], $vname);
            if ($metric['def_type'] == 0) {
                $commandLine .= ' XPORT:' . $vname[$metric['metric']] . ':' . $vname[$metric['metric']];
                $info = ['data' => [], 'legend' => $metric['metric_legend'], 'graph_type' => 'line', 'unit' => $metric['unit'], 'color' => $metric['ds_color_line'], 'negative' => false];
                if (isset($metric['ds_color_area'], $metric['ds_filled'])
                    && $metric['ds_filled'] === '1'
                ) {
                    $info['graph_type'] = 'area';
                }
                if (isset($metric['ds_invert']) && $metric['ds_invert'] == 1) {
                    $info['negative'] = true;
                }
                $metrics[] = $info;
            }
        }

        $descriptorspec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'a']];

        $process = proc_open($this->generalOpt['rrdtool_path_bin'] . ' - ', $descriptorspec, $pipes, null, null);
        if (false === is_resource($process)) {
            throw new RuntimeException();
        }
        fwrite($pipes[0], $commandLine);
        fclose($pipes[0]);

        $str = '';
        stream_set_blocking($pipes[1], 0);
        do {
            $status = proc_get_status($process);
            $str .= stream_get_contents($pipes[1]);
        } while ($status['running']);

        $str .= stream_get_contents($pipes[1]);

        // Remove text of the end of the stream
        $str = preg_replace("/<\/xport>(.*)$/s", '</xport>', $str);

        $exitCode = $status['exitcode'];

        proc_close($process);

        if ($exitCode != 0) {
            throw new RuntimeException();
        }

        // Transform XML to values
        $useXmlErrors = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($str);

        if (false === $xml) {
            throw new RuntimeException();
        }

        libxml_clear_errors();
        libxml_use_internal_errors($useXmlErrors);

        $rows = $xml->xpath('//xport/data/row');
        foreach ($rows as $row) {
            $time = null;
            $i = 0;
            foreach ($row->children() as $info) {
                if (is_null($time)) {
                    $time = (string) $info;
                } elseif (strtolower($info) === 'nan' || is_null($info)) {
                    $metrics[$i++]['data'][$time] = $info;
                } elseif ($metrics[$i]['negative']) {
                    $metrics[$i++]['data'][$time] = floatval((string) $info) * -1;
                } else {
                    $metrics[$i++]['data'][$time] = floatval((string) $info);
                }
            }
        }

        // Get legends
        $descriptorspec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'a']];

        $process = proc_open($this->generalOpt['rrdtool_path_bin'] . ' - ', $descriptorspec, $pipes, null, null);
        if (false === is_resource($process)) {
            throw new RuntimeException();
        }
        fwrite($pipes[0], $commandLegendLine);
        fclose($pipes[0]);

        $str = '';
        stream_set_blocking($pipes[1], 0);
        do {
            $status = proc_get_status($process);
            $str .= stream_get_contents($pipes[1]);
        } while ($status['running']);

        $str .= stream_get_contents($pipes[1]);

        $exitCode = $status['exitcode'];

        proc_close($process);

        if ($exitCode != 0) {
            throw new RuntimeException();
        }
        // Parsing
        $retLines = explode("\n", $str);
        foreach ($retLines as $retLine) {
            if (str_contains($retLine, '|')) {
                $infos = explode('|', $retLine);
                if (! isset($this->legends[$infos[0]])) {
                    $this->legends[$infos[0]] = ['extras' => []];
                }
                $this->legends[$infos[0]]['extras'][] = ['name' => $infos[1], 'value' => $infos[2]];
            }
        }

        return $metrics;
    }

    /**
     * Get limits lower and upper for a chart
     *
     * These values are defined on chart template
     *
     * @return array
     */
    public function getLimits()
    {
        $limits = ['min' => null, 'max' => null];
        if ($this->templateInformations['lower_limit'] !== '') {
            $limits['min'] = $this->templateInformations['lower_limit'];
        }
        if ($this->templateInformations['upper_limit'] !== '') {
            $limits['max'] = $this->templateInformations['upper_limit'];
        }

        return $limits;
    }

    /**
     * Get the base for this chart
     *
     * @return int
     */
    public function getBase()
    {
        return $this->templateInformations['base'] ?? 1000;
    }

    public function getLegends()
    {
        return $this->legends;
    }

    /**
     * @param $hostId
     * @param $serviceId
     * @param $dbc
     * @throws Exception
     * @return mixed
     */
    public static function getIndexId($hostId, $serviceId, $dbc)
    {
        $query = 'SELECT id FROM index_data '
            . 'WHERE host_id = :host '
            . 'AND service_id = :service';

        $stmt = $dbc->prepare($query);
        $stmt->bindParam(':host', $hostId, PDO::PARAM_INT);
        $stmt->bindParam(':service', $serviceId, PDO::PARAM_INT);
        $dbResult = $stmt->execute();
        if (! $dbResult) {
            throw new Exception('An error occured');
        }

        $row = $stmt->fetch();
        if (false == $row) {
            throw new OutOfRangeException();
        }

        return $row['id'];
    }
}
