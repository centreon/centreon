<?php

class Broker extends AbstractObjectXML {
    protected $engine = null;
    protected $broker = null;
    protected $generate_filename = null;
    protected $object_name = null;
    protected $attributes_select = '
        config_id,
        config_name,
        config_filename,
        config_write_timestamp,
        config_write_thread_id,
        ns_nagios_server,
        stats_activate,
        correlation_activate,
        event_queue_max_size,
        retention_path,
        command_file
    ';
    protected $attributes_select_parameters = '
        config_group,
        config_group_id,
        config_id,
        config_key,
        config_value,
        grp_level,
        subgrp_id,
        parent_grp_id
    ';
    protected $attributes_engine_parameters = '
        id,
        name,
        centreonbroker_module_path
    ';
    protected $exclude_parameters = array(
        'blockId'
    );
    protected $stmt_engine = null;
    protected $stmt_broker = null;
    protected $stmt_broker_parameters = null;
    protected $stmt_engine_parameters = null;
    
    private function generate($poller_id) {
        if (is_null($this->stmt_broker)) {
            $this->stmt_broker = $this->backend_instance->db->prepare("SELECT 
              $this->attributes_select
            FROM cfg_centreonbroker
            WHERE ns_nagios_server = :poller_id AND config_activate = '1'
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

        $result = $this->stmt_broker->fetchAll(PDO::FETCH_ASSOC);
        foreach ($result as $row) {
            $this->generate_filename = $row['config_filename'];
            $object = array();
            $flow_count = 0;

            $config_name = $row['config_name'];
            $retention_path = $row['retention_path'];
            $stats_activate = $row['stats_activate'];
            $correlation_activate = $row['correlation_activate'];

            # Base parameters
            $object['instance'] = $this->engine['id'];
            $object['instance_name'] = $this->engine['name'];
            $object['module_directory'] = $this->engine['broker_modules_path'];
            $object['log_timestamp'] = $row['config_write_timestamp'];
            $object['log_thread_id'] = $row['config_write_thread_id'];
            $object['event_queue_max_size'] = $row['event_queue_max_size'];
            $object['command_file'] = $row['command_file'];

            $this->stmt_broker_parameters->bindParam(':config_id', $row['config_id'], PDO::PARAM_INT);
            $this->stmt_broker_parameters->execute();
            $resultParameters = $this->stmt_broker_parameters->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);

            # Flow parameters
            foreach ($resultParameters as $key => $value) {
                foreach ($value as $subvalue) {
                    if (in_array($subvalue['config_key'], $this->exclude_parameters) || trim($subvalue['config_value']) == '') {
                        continue;
                    } else if ($subvalue['config_key'] == 'category') {
                        $object[$subvalue['config_group_id']][$key]['filters'][][$subvalue['config_key']] = $subvalue['config_value'];
                    } else {
                        $object[$subvalue['config_group_id']][$key][$subvalue['config_key']] = $subvalue['config_value'];
                    }
                    $flow_count++;
                }
            }

            # Failover parameters
            foreach ($object as &$subvalue) {
                foreach ($subvalue as $config_type => &$flow) {
                    if ($config_type == 'output' && isset($flow['name']) && !isset($flow['failover']) && isset($flow['type']) && $flow['type'] != 'file') {
                        $flow['failover'] = $flow['name'] . '-' . $config_type . '-failover';
                        $object[$flow_count][$config_type] = array(
                            'type' => 'file',
                            'name' => $flow['name'] . '-' . $config_type . '-failover',
                            'path' => $retention_path . '/' . $config_name . '_' . $flow['name'] . '.retention',
                            'protocol' => 'bbdo',
                            'compression' => 'auto',
                            'max_size' => '524288000'
                        );
                        $flow_count++;
                    }
                }
            }

            # Temporary parameters
            $object[$flow_count]['temporary'] = array(
                'type' => 'file',
                'name' => $config_name . '-temporary',
                'path' => $retention_path . '/' . $config_name . '.temporary',
                'protocol' => 'bbdo',
                'compression' => 'auto',
                'max_size' => '524288000'
            );
            $flow_count++;

            # Stats parameters
            if ($stats_activate == '1') {
                $object[$flow_count]['stats'] = array(
                    'type' => 'stats',
                    'name' => $config_name . '-stats',
                    'fifo' => $retention_path . '/' . $config_name . '.stats',
                );
                $flow_count++;
            }

            # Generate file
            $this->generateFile($object, true, 'centreonBroker');
            $this->writeFile($this->backend_instance->getPath());
        }

    }

    private function getEngineParameters($poller_id) {
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
        } catch (Exception $e) {
            throw new Exception('Exception received : ' .  $e->getMessage() . "\n");
        }
    }

    public function generateFromPoller($poller) {
        $this->generate($poller['id']);
    }
}

?>
