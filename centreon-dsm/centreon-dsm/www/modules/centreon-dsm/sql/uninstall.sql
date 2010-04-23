DROP TABLE mod_dsm_cct_relation;
DROP TABLE mod_dsm_cg_relation;
DROP TABLE mod_dsm_pool;

DELETE FROM topology WHERE topology_page IN ('507', '50711');
