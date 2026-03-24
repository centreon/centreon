--
--  Creation and config of central broker
--
INSERT INTO `cfg_centreonbroker` (`config_id`, `config_name`, `config_filename`, `config_write_timestamp`, `config_write_thread_id`, `log_directory`, `config_activate`, `ns_nagios_server`, `event_queue_max_size`, `cache_directory`, `command_file`, `daemon`) VALUES (1,'central-broker-master','central-broker.json','1','0', '/var/log/centreon-broker/', '1', 1 , 100000, '@centreonbroker_varlib@', '@centreonbroker_varlib@/command.sock', 1);
INSERT INTO `cfg_broker_input_output` (`config_id`, `tag`, `type_id`, `type_name`, `name`, `parameters`) VALUES (1, 'input', 3, 'ipv4', 'central-broker-master-input', '{"port":"5669","host":"","failover":"","retry_interval":"15","buffering_timeout":"0","protocol":"bbdo","tls":"auto","private_key":"","public_cert":"","ca_certificate":"","negotiation":"yes","one_peer_retention_mode":"no","compression":"auto","compression_level":"","compression_buffer":""}');
INSERT INTO `cfg_broker_input_output` (`config_id`, `tag`, `type_id`, `type_name`, `name`, `parameters`) VALUES (1, 'output', 34, 'unified_sql', 'central-broker-master-unified-sql', '{"db_type":"mysql","db_host":"@address@","db_port":"@port@","db_user":"@db_user@","db_password":"@db_password@","db_name":"@db_storage@","failover":"","retry_interval":"15","buffering_timeout":"0","queries_per_transaction":"","read_timeout":"","interval":"60","length":"15552000","rebuild_check_interval":"","store_in_data_bin":"yes","insert_in_index_data":"1","cleanup_check_interval":"","instance_timeout":""}');
INSERT INTO `cfg_broker_input_output` (`config_id`, `tag`, `type_id`, `type_name`, `name`, `parameters`) VALUES (1, 'output', 3, 'ipv4', 'centreon-broker-master-rrd', '{"port":"5670","host":"localhost","failover":"","retry_interval":"15","buffering_timeout":"0","protocol":"bbdo","tls":"no","private_key":"","public_cert":"","ca_certificate":"","negotiation":"yes","one_peer_retention_mode":"no","compression":"no","compression_level":"","compression_buffer":""}');
INSERT INTO `cfg_broker_input_output` (`config_id`, `tag`, `type_id`, `type_name`, `name`, `parameters`) SELECT 1, 'output', cb_type_id, 'event_script', 'central-broker-master-event-script', '{"script_path":"/usr/share/centreon/bin/console agent-configuration:host:create","timeout":"15","managed_event_ttl":"3600","filters_event":["neb:UnknownHost"]}' FROM `cb_type` WHERE `type_shortname` = 'event_script';

-- log
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (1,1,5);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (1,2,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (1,3,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (1,4,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (1,5,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (1,6,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (1,7,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (1,8,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (1,9,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (1,10,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (1,11,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (1,12,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (1,13,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (1,14,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (1,15,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (1,16,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (1,17,3);
--
--  Creation and config of central rrd
--

INSERT INTO `cfg_centreonbroker` (`config_id`, `config_name`, `config_filename`, `config_write_timestamp`, `config_write_thread_id`, `log_directory`, `config_activate`, `ns_nagios_server`, `event_queue_max_size`, `cache_directory`, `daemon`) VALUES (2,'central-rrd-master','central-rrd.json','1','0', '/var/log/centreon-broker/', '1',1 , 100000, '@centreonbroker_varlib@', 1);
INSERT INTO `cfg_broker_input_output` (`config_id`, `tag`, `type_id`, `type_name`, `name`, `parameters`) VALUES (2, 'input', 3, 'ipv4', 'central-rrd-master-input', '{"port":"5670","host":"","failover":"","retry_interval":"15","buffering_timeout":"0","protocol":"bbdo","tls":"auto","private_key":"","public_cert":"","ca_certificate":"","negotiation":"yes","one_peer_retention_mode":"no","compression":"auto","compression_level":"","compression_buffer":""}');
INSERT INTO `cfg_broker_input_output` (`config_id`, `tag`, `type_id`, `type_name`, `name`, `parameters`) VALUES (2, 'output', 13, 'rrd', 'central-rrd-master-output', '{"metrics_path":"@centreon_varlib@/metrics","status_path":"@centreon_varlib@/status","failover":"","retry_interval":"15","buffering_timeout":"0","path":"","port":"","write_metrics":"yes","write_status":"yes","store_in_data_bin":"yes","insert_in_index_data":"1"}');
--log
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (2,1,5);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (2,2,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (2,3,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (2,4,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (2,5,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (2,6,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (2,7,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (2,8,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (2,9,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (2,10,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (2,11,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (2,12,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (2,13,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (2,14,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (2,15,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (2,16,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (2,17,3);
--
--  Creation and config of central module
--
INSERT INTO `cfg_centreonbroker` (`config_id`, `config_name`, `config_filename`, `config_write_timestamp`, `config_write_thread_id`, `log_directory`, `config_activate`, `ns_nagios_server`, `event_queue_max_size`, `cache_directory`, `daemon`) VALUES (3,'central-module-master','central-module.json','0','0', '/var/log/centreon-broker/', '1', 1 , 100000, '@monitoring_var_lib@', 0);
INSERT INTO `cfg_broker_input_output` (`config_id`, `tag`, `type_id`, `type_name`, `name`, `parameters`) VALUES (3, 'output', 3, 'ipv4', 'central-module-master-output', '{"port":"5669","host":"localhost","failover":"","retry_interval":"15","buffering_timeout":"0","protocol":"bbdo","tls":"no","private_key":"","public_cert":"","ca_certificate":"","negotiation":"yes","one_peer_retention_mode":"no","compression":"no","compression_level":"","compression_buffer":""}');
--log
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (3,1,5);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (3,2,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (3,3,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (3,4,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (3,5,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (3,6,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (3,7,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (3,8,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (3,9,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (3,10,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (3,11,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (3,12,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (3,13,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (3,14,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (3,15,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (3,16,3);
INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`) VALUES (3,17,3);

UPDATE `nagios_server` SET `centreonbroker_cfg_path` = '@broker_etc@' WHERE `id` = 1;
UPDATE `nagios_server` SET `centreonbroker_module_path` = '@centreonbroker_lib@' WHERE `id` = 1;
