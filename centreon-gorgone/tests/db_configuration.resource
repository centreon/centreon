*** Variables ***
${DBName}           centreon
${DBHost}           localhost
${DBUser}           robotTest
${DBPass}           password
${DBPort}           3306
${sqlRequest}        select * from service JOIN host_service_relation ON service_service_id = service_id JOIN host ON host_service_relation.host_host_id = host.host_id WHERE service_description like 'Disk-/' 	AND host.host_alias LIKE 'gorgone_auto_discovery_test_%';
