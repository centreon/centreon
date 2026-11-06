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

declare(strict_types=1);

/**
 * Generate the SQL of an ACL dataset.
 *
 * Usage: php gen.php <hosts> <acl_groups> > dataset.sql
 *
 * The output only depends on the two arguments: no randomness, every relation comes from modulo arithmetic on
 * sequence numbers. Every object uses a fixed id at or above its base (ids.php), so the dataset is meant for an empty
 * database (the CI one), where the rows computed by centAcl.php can be compared with a frozen expected file. The
 * dataset creates its own poller, and keeps the foreign key checks enabled: a wrong reference fails the load.
 */
require_once __DIR__ . '/ids.php';

const SVC_PER_HOST = 10;
const HOST_TPL = 5;
const SVC_TPL = 20;
const HC = 10;
const SC = 5;
const POLLER = B;

/**
 * @param list<string> $argv
 *
 * @return array{int, int}
 */
function readArguments(array $argv): array
{
    $hosts = filter_var($argv[1] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $groups = filter_var($argv[2] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($hosts === false || $groups === false) {
        fwrite(STDERR, "Usage: php gen.php <hosts> <acl_groups> > dataset.sql\n");

        exit(1);
    }

    return [$hosts, $groups];
}

function seq(int $count, string $condition = ''): string
{
    return "FROM bench_seq WHERE n < {$count}" . ($condition !== '' ? " AND {$condition}" : '');
}

/**
 * @return list<string>
 */
function generate(int $hosts, int $groups): array
{
    $hg = max(intdiv($hosts, 20), 5);       // ~20 hosts per host group (+ half of hosts in a 2nd one)
    $sg = max(intdiv($hosts, 50), 5);       // one service per host goes in a service group
    $contacts = max(intdiv($hosts, 10), 20);
    $cg = max(intdiv($contacts, 5), 4);
    $res = $groups * 2;                      // two resource access rules per access group

    $seqMax = max($hosts * SVC_PER_HOST, $contacts, $res) + 1;
    $out = [];

    $digits = '(SELECT 0 d UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 '
        . 'UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9)';
    $digitTables = implode(', ', array_map(
        static fn (string $alias): string => "{$digits} {$alias}",
        ['a', 'b', 'c', 'd', 'e', 'f'],
    ));
    $out[] = 'DROP TABLE IF EXISTS bench_seq;';
    $out[] = 'CREATE TABLE bench_seq (n INT PRIMARY KEY);';
    $out[] = 'INSERT INTO bench_seq SELECT n FROM (SELECT a.d + b.d*10 + c.d*100 + d.d*1000 + e.d*10000 + f.d*100000 '
        . "AS n FROM {$digitTables}) digits WHERE n < {$seqMax};";

    // --- Poller, host templates, hosts
    $out[] = 'INSERT INTO nagios_server (id, uid, name, localhost, is_default, ns_activate) '
        . 'VALUES (' . POLLER . ', ' . POLLER . ", 'bench-poller', '0', 0, '1');";
    $out[] = 'INSERT INTO host (host_id, host_name, host_alias, host_register, host_activate) '
        . 'SELECT ' . B_HTPL . "+n, CONCAT('bench-htpl-', n), 'bench', '0', '1' " . seq(HOST_TPL) . ';';
    $out[] = 'INSERT INTO host (host_id, host_name, host_alias, host_address, host_register, host_activate) '
        . 'SELECT ' . B_HOST . "+n, CONCAT('bench-host-', n), 'bench', '127.0.0.1', '1', '1' " . seq($hosts) . ';';
    $out[] = 'INSERT INTO host_template_relation (host_host_id, host_tpl_id, `order`) '
        . 'SELECT ' . B_HOST . '+n, ' . B_HTPL . '+(n % ' . HOST_TPL . '), 1 ' . seq($hosts) . ';';
    $out[] = 'INSERT INTO ns_host_relation (nagios_server_id, host_host_id) '
        . 'SELECT ' . POLLER . ', ' . B_HOST . '+n ' . seq($hosts) . ';';

    // --- Service categories, service templates (category set on the template), services
    $out[] = 'INSERT INTO service_categories (sc_id, sc_name, sc_description, sc_activate) '
        . 'SELECT ' . B . "+n, CONCAT('bench-sc-', n), 'bench', '1' " . seq(SC) . ';';
    $out[] = 'INSERT INTO service (service_id, service_description, service_register, service_activate) '
        . 'SELECT ' . B_STPL . "+n, CONCAT('bench-stpl-', n), '0', '1' " . seq(SVC_TPL) . ';';
    $out[] = 'INSERT INTO service_categories_relation (service_service_id, sc_id) '
        . 'SELECT ' . B_STPL . '+n, ' . B . '+(n % ' . SC . ') ' . seq(SVC_TPL) . ';';
    $out[] = 'INSERT INTO service (service_id, service_description, service_template_model_stm_id, service_register, '
        . 'service_activate) '
        . 'SELECT ' . B_SVC . "+n, CONCAT('bench-svc-', n % " . SVC_PER_HOST . '), ' . B_STPL . '+(n % ' . SVC_TPL
        . "), '1', '1' " . seq($hosts * SVC_PER_HOST) . ';';
    $out[] = 'INSERT INTO host_service_relation (host_host_id, service_service_id) '
        . 'SELECT ' . B_HOST . '+(n DIV ' . SVC_PER_HOST . '), ' . B_SVC . '+n ' . seq($hosts * SVC_PER_HOST) . ';';

    // --- Host groups: every host in one group, every other host in a second one
    $out[] = 'INSERT INTO hostgroup (hg_id, hg_name, hg_alias, hg_activate) '
        . 'SELECT ' . B . "+n, CONCAT('bench-hg-', n), 'bench', '1' " . seq($hg) . ';';
    $out[] = 'INSERT INTO hostgroup_relation (hostgroup_hg_id, host_host_id) '
        . 'SELECT ' . B . "+(n % {$hg}), " . B_HOST . '+n ' . seq($hosts) . ';';
    $out[] = 'INSERT INTO hostgroup_relation (hostgroup_hg_id, host_host_id) '
        . 'SELECT ' . B . '+((n + ' . (intdiv($hg, 2) ?: 1) . ") % {$hg}), " . B_HOST . '+n '
        . seq($hosts, 'n % 2 = 0') . ';';

    // --- Service groups: the first service of each host
    $out[] = 'INSERT INTO servicegroup (sg_id, sg_name, sg_alias, sg_activate) '
        . 'SELECT ' . B . "+n, CONCAT('bench-sg-', n), 'bench', '1' " . seq($sg) . ';';
    $out[] = 'INSERT INTO servicegroup_relation (host_host_id, service_service_id, servicegroup_sg_id) '
        . 'SELECT ' . B_HOST . '+n, ' . B_SVC . '+n*' . SVC_PER_HOST . ', ' . B . "+(n % {$sg}) " . seq($hosts) . ';';

    // --- Host categories: on host templates (inherited) and directly on 10% of hosts
    $out[] = 'INSERT INTO hostcategories (hc_id, hc_name, hc_alias, hc_activate) '
        . 'SELECT ' . B . "+n, CONCAT('bench-hc-', n), 'bench', '1' " . seq(HC) . ';';
    $out[] = 'INSERT INTO hostcategories_relation (hostcategories_hc_id, host_host_id) '
        . 'SELECT ' . B . '+n, ' . B_HTPL . '+n ' . seq(HOST_TPL) . ';';
    $out[] = 'INSERT INTO hostcategories_relation (hostcategories_hc_id, host_host_id) '
        . 'SELECT ' . B . '+' . HOST_TPL . '+(n % ' . (HC - HOST_TPL) . '), ' . B_HOST . '+n '
        . seq($hosts, 'n % 10 = 0') . ';';

    // --- Contacts and contact groups
    $out[] = 'INSERT INTO contact (contact_id, contact_name, contact_alias, contact_email, contact_activate, '
        . 'contact_oreon, contact_admin, contact_register) '
        . 'SELECT ' . B . "+n, CONCAT('bench-contact-', n), CONCAT('bench-contact-', n), 'bench@localhost', '1', "
        . "'1', '0', 1 " . seq($contacts) . ';';
    $out[] = 'INSERT INTO contactgroup (cg_id, cg_name, cg_alias, cg_activate, cg_type) '
        . 'SELECT ' . B . "+n, CONCAT('bench-cg-', n), 'bench', '1', 'local' " . seq($cg) . ';';
    $out[] = 'INSERT INTO contactgroup_contact_relation (contact_contact_id, contactgroup_cg_id) '
        . 'SELECT ' . B . '+n, ' . B . "+(n % {$cg}) " . seq($contacts) . ';';

    // --- Access groups: linked to a slice of contacts and one contact group
    $out[] = 'INSERT INTO acl_groups (acl_group_id, acl_group_name, acl_group_alias, acl_group_changed, '
        . 'acl_group_activate, cloud_specific, all_contacts, all_contact_groups) '
        . 'SELECT ' . B . "+n, CONCAT('bench-ag-', n), 'bench', 1, '1', 0, 0, 0 " . seq($groups) . ';';
    $out[] = 'INSERT INTO acl_group_contacts_relations (contact_contact_id, acl_group_id) '
        . 'SELECT ' . B . '+n, ' . B . "+(n % {$groups}) " . seq($contacts) . ';';
    $out[] = 'INSERT INTO acl_group_contactgroups_relations (cg_cg_id, acl_group_id) '
        . 'SELECT ' . B . "+(n % {$cg}), " . B . '+n ' . seq($groups) . ';';

    // --- Resource access rules, a mix of the shapes seen in production (r = n % 10):
    //   0-3 host groups only | 4-5 explicit hosts + exclusions | 6-7 service groups
    //   8 host groups + host category filter | 9 host groups + service category filter
    $out[] = 'INSERT INTO acl_resources (acl_res_id, acl_res_name, acl_res_alias, acl_res_activate, changed, locked, '
        . 'cloud_specific, all_image_folders) '
        . 'SELECT ' . B . "+n, CONCAT('bench-res-', n), 'bench', '1', 1, 0, 0, 0 " . seq($res) . ';';
    $out[] = 'INSERT INTO acl_res_group_relations (acl_res_id, acl_group_id, `order`) '
        . 'SELECT ' . B . '+n, ' . B . '+(n DIV 2), 0 ' . seq($res) . ';';

    $hgPerRes = max(intdiv($hg, 10), 1);
    $out[] = 'INSERT INTO acl_resources_hg_relations (hg_hg_id, acl_res_id) '
        . 'SELECT ' . B . "+((r.n * {$hgPerRes} + k.n) % {$hg}), " . B . '+r.n FROM bench_seq r '
        . "JOIN bench_seq k ON k.n < {$hgPerRes} WHERE r.n < {$res} AND (r.n % 10 < 4 OR r.n % 10 >= 8);";

    $hostsPerRes = max(intdiv($hosts, 20), 1);
    $out[] = 'INSERT INTO acl_resources_host_relations (host_host_id, acl_res_id) '
        . 'SELECT ' . B_HOST . "+((r.n * {$hostsPerRes} + k.n) % {$hosts}), " . B . '+r.n FROM bench_seq r '
        . "JOIN bench_seq k ON k.n < {$hostsPerRes} WHERE r.n < {$res} AND r.n % 10 IN (4, 5);";
    $out[] = 'INSERT INTO acl_resources_hostex_relations (host_host_id, acl_res_id) '
        . 'SELECT ' . B_HOST . "+((r.n * {$hostsPerRes} + k.n) % {$hosts}), " . B . '+r.n FROM bench_seq r '
        . "JOIN bench_seq k ON k.n < {$hostsPerRes} AND k.n % 10 = 0 WHERE r.n < {$res} AND r.n % 10 IN (4, 5);";

    $sgPerRes = max(intdiv($sg, 10), 1);
    $out[] = 'INSERT INTO acl_resources_sg_relations (sg_id, acl_res_id) '
        . 'SELECT ' . B . "+((r.n * {$sgPerRes} + k.n) % {$sg}), " . B . '+r.n FROM bench_seq r '
        . "JOIN bench_seq k ON k.n < {$sgPerRes} WHERE r.n < {$res} AND r.n % 10 IN (6, 7);";

    $out[] = 'INSERT INTO acl_resources_hc_relations (hc_id, acl_res_id) '
        . 'SELECT ' . B . '+(n % ' . HC . '), ' . B . '+n ' . seq($res, 'n % 10 = 8') . ';';
    $out[] = 'INSERT INTO acl_resources_sc_relations (sc_id, acl_res_id) '
        . 'SELECT ' . B . '+(n % ' . SC . '), ' . B . '+n ' . seq($res, 'n % 10 = 9') . ';';

    $out[] = 'DROP TABLE bench_seq;';

    fwrite(STDERR, sprintf(
        "-- hosts=%d services=%d hg=%d sg=%d contacts=%d cg=%d acl_groups=%d acl_resources=%d\n",
        $hosts,
        $hosts * SVC_PER_HOST,
        $hg,
        $sg,
        $contacts,
        $cg,
        $groups,
        $res,
    ));

    return $out;
}

[$hosts, $groups] = readArguments($argv);
echo implode("\n", generate($hosts, $groups)) . "\n";
