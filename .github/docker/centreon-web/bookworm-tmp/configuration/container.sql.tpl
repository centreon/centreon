UPDATE `cfg_centreonbroker_info` SET `config_value`='cbd' WHERE `config_id` IN (select `config_id` FROM `cfg_centreonbroker_info` WHERE `config_value` = 'central-module-master-output') AND `config_key` = 'host';
UPDATE cfg_centreonbroker_info SET config_value = '${DB_HOST}' WHERE config_key = 'db_host';
DELETE FROM cfg_centreonbroker_info WHERE config_group = 'output' AND config_group_id = '1';
UPDATE cfg_centreonbroker_info SET config_id = '1', config_group_id = 1 WHERE config_id = '2' and config_group = 'output';
DELETE FROM cfg_centreonbroker WHERE config_name = 'central-rrd-master';
UPDATE options SET `value` = 'gorgone' WHERE `key` = 'gorgone_api_address';
