DROP TABLE `mod_dsm_pool`;
DROP TABLE centreon_storage.`mod_dsm_cache`;
DROP TABLE centreon_storage.`mod_dsm_locks`;
DROP TABLE centreon_storage.`mod_dsm_history`;

DELETE FROM topology WHERE topology_page IN ('50711');
DELETE FROM topology WHERE topology_name = 'Dynamic Services';
