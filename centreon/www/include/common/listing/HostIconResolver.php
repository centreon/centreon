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
 * The walk matches getMyHostExtendedInfoImage(): every direct template is
 * followed, in `order`, and a template that carries no icon is exhausted
 * through its own chain before the next one is looked at. An object linked to
 * a generic template first and to an icon-bearing pack second must still show
 * the pack's icon.
 *
 * Two properties make this a batch resolver rather than a per-row recursive
 * lookup:
 *
 * - `host_template_relation` is not guaranteed acyclic (a cycle is reachable
 *   through CLAPI or an import). Each object therefore carries its own visited
 *   set and the walk is capped: an unguarded recursion would run until the
 *   worker exhausts its memory, an E_ERROR no `catch (Throwable)` can rescue.
 *   The legacy CentreonHost inheritance helpers guard cycles the same way
 *   (`$alreadyProcessed`).
 * - one query serves every row of the page at each step, so a listing of N rows
 *   costs one query per step instead of N × the size of its template chain.
 */
final class HostIconResolver
{
    /** Safety net on top of the per-object visited set. */
    private const MAX_NODES_PER_OBJECT = 50;

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

        return self::walk(
            $hostIds,
            self::fetchDirectIcons($db),
            static fn (array $nodes): array => self::fetchTemplates($db, $nodes)
        );
    }

    /**
     * The walk itself, with its two data sources handed in: the icons of the
     * objects that carry one, and a callable returning the templates of a batch
     * of nodes. Kept apart from the queries so the inheritance rules can be
     * exercised on their own.
     *
     * @param int[] $hostIds Host or host-template ids to resolve
     * @param array<int, string> $directIcons Icon path of the objects defining one
     * @param callable(int[]): array<int, int[]> $fetchTemplates Templates of the given nodes, in `order`
     *
     * @return array<int, string> Icon path indexed by requested id
     */
    public static function walk(array $hostIds, array $directIcons, callable $fetchTemplates): array
    {
        // $pending maps each requested id to the nodes it still has to inspect,
        // in depth-first order; $visited holds the nodes already inspected for it.
        $resolved = [];
        $pending  = [];
        $visited  = [];
        foreach ($hostIds as $hostId) {
            if (isset($directIcons[$hostId])) {
                $resolved[$hostId] = $directIcons[$hostId];

                continue;
            }
            $pending[$hostId] = [$hostId];
            $visited[$hostId] = [$hostId => true];
        }

        for ($step = 0; $pending !== [] && $step < self::MAX_NODES_PER_OBJECT; $step++) {
            $heads = [];
            foreach ($pending as $stack) {
                $heads[] = $stack[0];
            }
            $templates = $fetchTemplates(array_values(array_unique($heads)));

            foreach ($pending as $hostId => $stack) {
                $node = array_shift($stack);

                // Checked before descending, so a template's own icon wins over
                // anything further up its chain.
                if (isset($directIcons[$node])) {
                    $resolved[$hostId] = $directIcons[$node];
                    unset($pending[$hostId]);

                    continue;
                }

                $children = [];
                foreach ($templates[$node] ?? [] as $templateId) {
                    if (isset($visited[$hostId][$templateId])) {
                        continue;
                    }
                    $visited[$hostId][$templateId] = true;
                    $children[] = $templateId;
                }

                // Prepended: the first template's chain is exhausted before the
                // second template of the same object is considered.
                $stack = array_merge($children, $stack);

                if ($stack === []) {
                    unset($pending[$hostId]);

                    continue;
                }

                $pending[$hostId] = $stack;
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
     * Templates of each given object, in `order`, in a single query.
     *
     * @param int[] $hostIds
     *
     * @return array<int, int[]>
     */
    private static function fetchTemplates(CentreonDB $db, array $hostIds): array
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

        $templates = [];
        foreach ($rows as $row) {
            $templates[(int) $row['host_host_id']][] = (int) $row['host_tpl_id'];
        }

        return $templates;
    }
}
