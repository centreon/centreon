ALTER TABLE centreon_storage.mod_dsm_cache DROP KEY IF EXISTS `cache_mult_idx`;
ALTER TABLE centreon_storage.mod_dsm_cache ADD KEY IF NOT EXISTS `cache_host_id` (`host_id`);
