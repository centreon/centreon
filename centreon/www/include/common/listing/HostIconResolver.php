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

use Adaptation\Database\Connection\Collection\QueryParameters;

require_once __DIR__ . '/AjaxListingHelper.php';

/**
 * Resolves the icon of hosts and host templates, inheriting from the template
 * chain when the object defines none of its own.
 *
 * Two properties matter here, and both are why this is a batch resolver rather
 * than a per-row recursive lookup:
 *
 * - `host_template_relation` is not guaranteed acyclic (a cycle is reachable
 *   through CLAPI or an import). Each object therefore carries its own visited
 *   set and the walk is capped in depth: an unguarded recursion would run until
 *   the worker exhausts its memory, an E_ERROR no `catch (Throwable)` can
 *   rescue. The legacy CentreonHost inheritance helpers guard cycles the same
 *   way (`$alreadyProcessed`).
 * - the chain is walked one level at a time for the whole page, so a listing of
 *   N rows costs one query per level of inheritance instead of N × depth.
 */
final class HostIconResolver
{
    /** Safety net on top of the per-object visited set. */
    private const MAX_DEPTH = 30;

    /**
     * @param int[] $hostIds Host or host-template ids to resolve
     *
     * @return array<int, string> Icon path indexed by requested id; ids with no
     *                            icon anywhere in their chain are absent
     */
    public static function resolve(CentreonDB $db, array $hostIds): array
    {
        if ($hostIds === []) {
            return [];
        }

        $directIcons = self::fetchDirectIcons($db);

        // $walking maps each requested id to the chain node currently being
        // inspected for it; $visited holds the nodes already inspected for it.
        $resolved = [];
        $walking  = [];
        $visited  = [];
        foreach ($hostIds as $hostId) {
            if (isset($directIcons[$hostId])) {
                $resolved[$hostId] = $directIcons[$hostId];

                continue;
            }
            $walking[$hostId] = $hostId;
            $visited[$hostId] = [$hostId => true];
        }

        for ($depth = 0; $walking !== [] && $depth < self::MAX_DEPTH; $depth++) {
            $parents = self::fetchFirstParents($db, array_unique(array_values($walking)));

            foreach ($walking as $hostId => $node) {
                $parent = $parents[$node] ?? null;

                // Top of the chain reached, or node already inspected for this id
                // (cycle): nothing left to inherit from.
                if ($parent === null || isset($visited[$hostId][$parent])) {
                    unset($walking[$hostId]);

                    continue;
                }

                $visited[$hostId][$parent] = true;

                if (isset($directIcons[$parent])) {
                    $resolved[$hostId] = $directIcons[$parent];
                    unset($walking[$hostId]);

                    continue;
                }

                $walking[$hostId] = $parent;
            }
        }

        return $resolved;
    }

    /**
     * Icon of every object that defines one directly. Loaded whole because the
     * walk reaches arbitrary ancestors, and the filter on `ehi_icon_image`
     * keeps this to the objects that actually carry an icon.
     *
     * @return array<int, string>
     */
    private static function fetchDirectIcons(CentreonDB $db): array
    {
        $icons = [];
        $rows = $db->fetchAllAssociative(
            <<<'SQL'
                SELECT ehi.host_host_id, CONCAT(vid.dir_alias, '/', vi.img_path) AS icon_path
                FROM extended_host_information ehi
                INNER JOIN view_img vi ON ehi.ehi_icon_image = vi.img_id
                INNER JOIN view_img_dir_relation vidr ON vi.img_id = vidr.img_img_id
                INNER JOIN view_img_dir vid ON vidr.dir_dir_parent_id = vid.dir_id
                WHERE ehi.ehi_icon_image IS NOT NULL
                SQL
        );
        foreach ($rows as $row) {
            $icons[(int) $row['host_host_id']] = './img/media/' . $row['icon_path'];
        }

        return $icons;
    }

    /**
     * First template (lowest `order`) of each given object, in a single query.
     *
     * @param int[] $hostIds
     *
     * @return array<int, int>
     */
    private static function fetchFirstParents(CentreonDB $db, array $hostIds): array
    {
        $in = AjaxListingHelper::buildIntInClause($hostIds, 'icon_hid');

        $rows = $db->fetchAllAssociative(
            <<<SQL
                SELECT host_host_id, host_tpl_id
                FROM host_template_relation
                WHERE host_host_id IN ({$in['clause']})
                ORDER BY host_host_id, `order`
                SQL,
            QueryParameters::create($in['parameters'])
        );

        $parents = [];
        foreach ($rows as $row) {
            // Ordered by `order`, so the first row seen for an id is its first template.
            $parents[(int) $row['host_host_id']] ??= (int) $row['host_tpl_id'];
        }

        return $parents;
    }
}
