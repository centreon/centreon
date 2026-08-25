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
use Adaptation\Log\Enum\LogChannelEnum;
use Adaptation\Log\Logger;

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
 *   through CLAPI or an import), so each object carries its own visited set —
 *   that alone makes the walk finite, and is how the legacy CentreonHost
 *   inheritance helpers guard cycles too (`$alreadyProcessed`). MAX_NODES_PER_OBJECT
 *   is a separate ceiling on depth: past it the object keeps the default glyph,
 *   which resolve() logs, since only abnormal data reaches it.
 * - every row of the page shares the same two queries at each step — the icons
 *   of the nodes being looked at, and their templates — so a listing of N rows
 *   costs a constant number of queries per step instead of N × the size of its
 *   template chain. Both are scoped to those nodes, never to the whole table.
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

        $truncated = null;
        $resolved  = self::walk(
            $hostIds,
            static fn (array $nodes): array => self::fetchDirectIcons($db, $nodes),
            static fn (array $nodes): array => self::fetchTemplates($db, $nodes),
            $truncated
        );

        if (! empty($truncated)) {
            // Only a chain deeper than the cap, or a cycle, gets here. The symptom
            // an operator sees — this row shows the default glyph while the object's
            // own form shows the right icon — is unexplainable without this line.
            Logger::create(LogChannelEnum::WEB)->warning(
                'Host icon inheritance gave up: template chain deeper than the node cap',
                ['objectIds' => $truncated, 'cap' => self::MAX_NODES_PER_OBJECT]
            );
        }

        return $resolved;
    }

    /**
     * The walk itself, with its two data sources handed in as callables: the
     * icons of a batch of nodes, and their templates in `order`. Kept apart
     * from the queries so the inheritance rules can be exercised on their own.
     *
     * @param int[] $hostIds Host or host-template ids to resolve
     * @param callable(int[]): array<int, string> $fetchIcons Icon path of those given nodes that define one
     * @param callable(int[]): array<int, int[]> $fetchTemplates Templates of the given nodes, in `order`
     *
     * @param int[]|null $truncated Set to the ids whose chain hit the depth cap,
     *                              so the caller can report data it cannot resolve
     *
     * @return array<int, string> Icon path indexed by requested id
     */
    public static function walk(
        array $hostIds,
        callable $fetchIcons,
        callable $fetchTemplates,
        ?array &$truncated = null,
    ): array {
        // $pending maps each requested id to the nodes it still has to inspect,
        // in depth-first order; $visited holds the nodes already enqueued for it,
        // so a node is never queued twice. $icons caches what the icon source
        // answered — a null meaning "asked about, carries none" — so a template
        // shared by many rows is only ever asked about once.
        $resolved = [];
        $pending  = [];
        $visited  = [];
        $icons    = self::askIcons($fetchIcons, $hostIds, []);
        foreach ($hostIds as $hostId) {
            if (isset($icons[$hostId])) {
                $resolved[$hostId] = $icons[$hostId];

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
            $heads     = array_values(array_unique($heads));
            $icons     = self::askIcons($fetchIcons, $heads, $icons);
            $templates = $fetchTemplates($heads);

            foreach ($pending as $hostId => $stack) {
                $node = array_shift($stack);

                // Checked before descending, so a template's own icon wins over
                // anything further up its chain.
                if (isset($icons[$node])) {
                    $resolved[$hostId] = $icons[$node];
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

        // Whatever is still pending ran out of steps rather than out of chain, and
        // would otherwise be indistinguishable from an object carrying no icon.
        $truncated = array_keys($pending);

        return $resolved;
    }

    /**
     * Ask the icon source about the nodes it has not been asked about yet, and
     * keep the answer. Nodes carrying no icon are cached as null: a template
     * sitting in many chains is then asked about once instead of once per row.
     *
     * @param callable(int[]): array<int, string> $fetchIcons
     * @param int[] $nodes
     * @param array<int, string|null> $known
     *
     * @return array<int, string|null>
     */
    private static function askIcons(callable $fetchIcons, array $nodes, array $known): array
    {
        $unknown = [];
        foreach ($nodes as $node) {
            if (! array_key_exists($node, $known)) {
                $unknown[] = $node;
            }
        }

        if ($unknown === []) {
            return $known;
        }

        $found = $fetchIcons($unknown);
        foreach ($unknown as $node) {
            $known[$node] = $found[$node] ?? null;
        }

        return $known;
    }

    /**
     * Icon of those given objects that define one directly. Scoped to the nodes
     * the walk is currently looking at: the whole table would otherwise be read
     * on every listing request, and the listing refreshes on a timer.
     *
     * @param int[] $hostIds
     *
     * @return array<int, string>
     */
    private static function fetchDirectIcons(CentreonDB $db, array $hostIds): array
    {
        if ($hostIds === []) {
            return [];
        }

        $in = AjaxListingHelper::buildIntInClause($hostIds, 'icon_oid');

        $icons = [];
        $rows = $db->fetchAllAssociative(
            <<<SQL
                SELECT ehi.host_host_id, vid.dir_alias, vi.img_path
                FROM extended_host_information ehi
                INNER JOIN view_img vi ON ehi.ehi_icon_image = vi.img_id
                INNER JOIN view_img_dir_relation vidr ON vi.img_id = vidr.img_img_id
                INNER JOIN view_img_dir vid ON vidr.dir_dir_parent_id = vid.dir_id
                WHERE ehi.ehi_icon_image IS NOT NULL AND ehi.host_host_id IN ({$in['clause']})
                SQL,
            QueryParameters::create($in['parameters'])
        );
        foreach ($rows as $row) {
            // Both halves are required, as the legacy helper required them:
            // concatenating a missing one yields './img/media//file.png', a
            // path that resolves to nothing.
            $dir  = (string) $row['dir_alias'];
            $file = (string) $row['img_path'];
            if ($dir === '' || $file === '') {
                // A media row with only half a path is inconsistent data, not a
                // missing icon: without a trace, "this object lost its icon" has
                // no explanation anywhere.
                Logger::create(LogChannelEnum::WEB)->warning(
                    'Host icon skipped: media row carries an empty directory or file name',
                    ['objectId' => (int) $row['host_host_id']]
                );

                continue;
            }
            $icons[(int) $row['host_host_id']] = './img/media/' . $dir . '/' . $file;
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
        if ($hostIds === []) {
            return [];
        }

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
