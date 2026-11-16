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

use Core\Common\Infrastructure\FeatureFlags;

/**
 * Writes the recurring downtimes of a poller in recurringdowntimes.cfg, for Engine to schedule them itself
 * (feature flag "engine_recurring_downtimes"; without it, the downtimeManager cron sends them).
 *
 * @class RecurringDowntime
 */
class RecurringDowntime extends AbstractObject
{
    public const FEATURE_FLAG = 'engine_recurring_downtimes';
    public const FILENAME = 'recurringdowntimes.cfg';
    private const NTH_CYCLES = ['first', 'second', 'third', 'fourth', 'last'];

    /** @var string */
    protected $generate_filename = self::FILENAME;

    /** @var string */
    protected string $object_name = 'recurringdowntime';

    /**
     * The file is created only when a rule is written: no file, no cfg_file entry.
     */
    public function reset(): void
    {
        $this->close_file();
        $this->exported = [];
    }

    /**
     * To call after the hosts of the poller are generated: targets are restricted to the generated hosts/services.
     *
     * @throws PDOException
     */
    public function generateFromPollerId(int $pollerId): void
    {
        if (! $this->kernel->getContainer()->get(FeatureFlags::class)->isEnabled(self::FEATURE_FLAG)) {
            return;
        }

        $servicesByHost = Service::getInstance($this->dependencyInjector)->getGeneratedServices();
        $hostIds = array_map('intval', Host::getInstance($this->dependencyInjector)->getGeneratedHosts());

        $hostgroupMembers = [];
        foreach (Hostgroup::getInstance($this->dependencyInjector)->getHostgroups() as $hgId => $hostgroup) {
            $hostgroupMembers[(int) $hgId] = array_map('intval', array_keys($hostgroup['members']));
        }
        $servicegroupMembers = [];
        foreach (Servicegroup::getInstance($this->dependencyInjector)->getServicegroups() as $sgId => $servicegroup) {
            // members_cache keys are "<host_id>_<service_id>"
            $servicegroupMembers[(int) $sgId] = array_map(
                static fn (string $key): array => array_map('intval', explode('_', $key)),
                array_map('strval', array_keys($servicegroup['members_cache']))
            );
        }

        $timezone = Timezone::getInstance($this->dependencyInjector);
        foreach ($this->getRules() as $dtId => $rule) {
            $targets = self::resolveTargets(
                $rule['relations'],
                $hostIds,
                $servicesByHost,
                $hostgroupMembers,
                $servicegroupMembers
            );
            $periods = array_merge(...array_map(self::formatPeriod(...), $rule['periods']));
            if ($periods === [] || ($targets['host_ids'] === [] && $targets['service_ids'] === [])) {
                continue;
            }

            $timezoneName = $rule['timezone_id'] !== null ? $timezone->getTimezoneFromId($rule['timezone_id']) : null;
            $this->generateObjectInFile(
                [
                    'downtime_id' => $dtId,
                    'downtime_name' => $rule['name'],
                    'timezone' => $timezoneName !== null ? ':' . $timezoneName : null,
                    'host_ids' => $targets['host_ids'],
                    'service_ids' => $targets['service_ids'],
                    'period' => $periods,
                ],
                $dtId
            );
        }

        if ($this->exported !== []) {
            Engine::getInstance($this->dependencyInjector)->addCfgPath(self::FILENAME);
        }
        $this->close_file();
    }

    /**
     * Converts one downtime_period row into "period" values (several for a "specific date" on several days).
     * A row that Engine would reject (bad time range, unknown day) is skipped: an invalid object fails the export.
     *
     * @param array<string, mixed> $period downtime_period row
     *
     * @return list<string>
     */
    public static function formatPeriod(array $period): array
    {
        $start = self::formatTime((string) $period['dtp_start_time']);
        $end = self::formatTime((string) $period['dtp_end_time']);
        if ($start === null || $end === null || strcmp($start, $end) >= 0) {
            return [];
        }
        $type = (string) $period['dtp_fixed'] === '0'
            ? 'flexible:' . (int) $period['dtp_duration'] // stored in seconds, as the cron sends it
            : 'fixed';
        $suffix = ' ' . $start . '-' . $end . ' ' . $type;

        $daysOfWeek = (string) ($period['dtp_day_of_week'] ?? '');
        $daysOfMonth = (string) ($period['dtp_day_of_month'] ?? '');
        $cycle = (string) ($period['dtp_month_cycle'] ?? '');

        // Same precedence as CentreonDowntimeBroker::getApproachingDowntimes()
        if (preg_match('/^\d(,\d)*$/', $daysOfWeek) && in_array($cycle, ['all', 'none'], true)) {
            $days = self::filterDays($daysOfWeek, 7);

            return $days === [] ? [] : ['weekly:' . implode(',', $days) . $suffix];
        }
        if (preg_match('/^\d+(,\d+)*$/', $daysOfMonth)) {
            $days = self::filterDays($daysOfMonth, 31);

            return $days === [] ? [] : ['monthly:' . implode(',', $days) . $suffix];
        }
        if (preg_match('/^\d(,\d)*$/', $daysOfWeek) && in_array($cycle, self::NTH_CYCLES, true)) {
            return array_map(
                static fn (int $day): string => 'nth:' . $cycle . ':' . $day . $suffix,
                self::filterDays($daysOfWeek, 7)
            );
        }

        return [];
    }

    /**
     * @param array{hosts: int[], hostgroups: int[], services: list<array{int, int}>, servicegroups: int[]} $relations
     * @param int[] $hostIds hosts generated on the poller
     * @param array<int, int[]> $servicesByHost services generated on the poller, by host
     * @param array<int, int[]> $hostgroupMembers hosts of the poller, by hostgroup
     * @param array<int, list<array{int, int}>> $servicegroupMembers [host_id, service_id] of the poller, by servicegroup
     *
     * @return array{host_ids: int[], service_ids: string[]}
     */
    public static function resolveTargets(
        array $relations,
        array $hostIds,
        array $servicesByHost,
        array $hostgroupMembers,
        array $servicegroupMembers,
    ): array {
        $hosts = [];
        foreach ($relations['hosts'] as $hostId) {
            if (in_array((int) $hostId, $hostIds, true)) {
                $hosts[(int) $hostId] = true;
            }
        }
        foreach ($relations['hostgroups'] as $hgId) {
            foreach ($hostgroupMembers[(int) $hgId] ?? [] as $hostId) {
                $hosts[(int) $hostId] = true;
            }
        }

        $services = [];
        foreach ($relations['services'] as [$hostId, $serviceId]) {
            if (in_array((int) $serviceId, array_map('intval', $servicesByHost[(int) $hostId] ?? []), true)) {
                $services[(int) $hostId . ':' . (int) $serviceId] = [(int) $hostId, (int) $serviceId];
            }
        }
        foreach ($relations['servicegroups'] as $sgId) {
            foreach ($servicegroupMembers[(int) $sgId] ?? [] as [$hostId, $serviceId]) {
                $services[$hostId . ':' . $serviceId] = [$hostId, $serviceId];
            }
        }

        $hostIdList = array_keys($hosts);
        sort($hostIdList);
        usort($services, static fn (array $a, array $b): int => $a <=> $b);

        return [
            'host_ids' => $hostIdList,
            'service_ids' => array_map(static fn (array $pair): string => $pair[0] . ':' . $pair[1], $services),
        ];
    }

    /**
     * @param array{downtime_id: int, downtime_name?: ?string, timezone?: ?string, host_ids: int[],
     *     service_ids: string[], period: string[]} $object
     */
    public static function formatObject(array $object): string
    {
        $line = static fn (string $key, string $value): string => sprintf("    %-30s %s\n", $key, $value);

        $text = "\ndefine recurringdowntime {\n" . $line('downtime_id', (string) $object['downtime_id']);
        // Only for Engine logs: keep it on one line and out of the ';' comment syntax
        $name = trim(preg_replace('/[;\s]+/', ' ', (string) ($object['downtime_name'] ?? '')));
        if ($name !== '') {
            $text .= $line('downtime_name', $name);
        }
        if (! empty($object['timezone'])) {
            $text .= $line('timezone', $object['timezone']);
        }
        if ($object['host_ids'] !== []) {
            $text .= $line('host_ids', implode(',', $object['host_ids']));
        }
        if ($object['service_ids'] !== []) {
            $text .= $line('service_ids', implode(',', $object['service_ids']));
        }
        foreach ($object['period'] as $period) {
            $text .= $line('period', $period);
        }

        return $text . "}\n";
    }

    /**
     * @param array<string, mixed> $object
     */
    protected function writeObject($object): void
    {
        fwrite($this->fp, $this->toUTF8(self::formatObject($object)));
    }

    /**
     * "HH:MM[:SS]" (MySQL TIME) to "HH:MM", up to "24:00"; seconds are ignored as the cron does.
     */
    private static function formatTime(string $time): ?string
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})(:\d{2})?$/', $time, $matches)) {
            return null;
        }
        $hours = (int) $matches[1];
        $minutes = (int) $matches[2];
        if ($minutes > 59 || $hours > 24 || ($hours === 24 && $minutes !== 0)) {
            return null;
        }

        return sprintf('%02d:%02d', $hours, $minutes);
    }

    /**
     * @return int[] sorted unique days in 1..$max; day 0 of a week is Sunday (7), as the cron reads it
     */
    private static function filterDays(string $days, int $max): array
    {
        $result = [];
        foreach (explode(',', $days) as $day) {
            $day = (int) $day;
            if ($max === 7 && $day === 0) {
                $day = 7;
            }
            if ($day >= 1 && $day <= $max) {
                $result[$day] = $day;
            }
        }
        sort($result);

        return $result;
    }

    /**
     * Active downtimes with their active periods and their relations.
     *
     * @throws PDOException
     *
     * @return array<int, array{name: string, timezone_id: ?int, periods: list<array<string, mixed>>,
     *     relations: array{hosts: int[], hostgroups: int[], services: list<array{int, int}>, servicegroups: int[]}}>
     */
    private function getRules(): array
    {
        $db = $this->backend_instance->db;
        $stmt = $db->prepare(
            <<<'SQL'
                SELECT dt.dt_id, dt.dt_name, dt.dt_timezone_id,
                    dtp.dtp_start_time, dtp.dtp_end_time, dtp.dtp_day_of_week, dtp.dtp_month_cycle,
                    dtp.dtp_day_of_month, dtp.dtp_fixed, dtp.dtp_duration
                FROM downtime dt
                INNER JOIN downtime_period dtp ON dtp.dt_id = dt.dt_id
                WHERE dt.dt_activate = '1' AND dtp.dtp_activate = '1'
                ORDER BY dt.dt_id, dtp.dtp_start_time, dtp.dtp_end_time, dtp.dtp_day_of_week, dtp.dtp_day_of_month
                SQL
        );
        $stmt->execute();
        $rules = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $dtId = (int) $row['dt_id'];
            $rules[$dtId] ??= [
                'name' => (string) $row['dt_name'],
                'timezone_id' => $row['dt_timezone_id'] !== null ? (int) $row['dt_timezone_id'] : null,
                'periods' => [],
                'relations' => ['hosts' => [], 'hostgroups' => [], 'services' => [], 'servicegroups' => []],
            ];
            $rules[$dtId]['periods'][] = $row;
        }
        if ($rules === []) {
            return [];
        }

        $relationQueries = [
            'hosts' => 'SELECT dt_id, host_host_id FROM downtime_host_relation',
            'hostgroups' => 'SELECT dt_id, hg_hg_id FROM downtime_hostgroup_relation',
            'services' => 'SELECT dt_id, host_host_id, service_service_id FROM downtime_service_relation',
            'servicegroups' => 'SELECT dt_id, sg_sg_id FROM downtime_servicegroup_relation',
        ];
        foreach ($relationQueries as $type => $query) {
            $stmt = $db->prepare($query);
            $stmt->execute();
            foreach ($stmt->fetchAll(PDO::FETCH_NUM) as $row) {
                $dtId = (int) $row[0];
                if (! isset($rules[$dtId])) {
                    continue;
                }
                $rules[$dtId]['relations'][$type][] = $type === 'services'
                    ? [(int) $row[1], (int) $row[2]]
                    : (int) $row[1];
            }
        }

        return $rules;
    }
}
