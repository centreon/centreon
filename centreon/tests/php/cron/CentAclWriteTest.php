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

namespace Tests\cron;

use Adaptation\Database\Connection\Collection\QueryParameters;
use CentreonDB;
use CentreonDBStatement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../cron/centAcl-Write.php';

class CentAclWriteTest extends TestCase
{
    private const GROUP_ID = 12;

    /** @var list<array{string, list<int|null>}> DELETE and INSERT statements run, with their parameters */
    private array $writes = [];

    /** @var list<string> transaction calls and the read of the rows of the group, in order */
    private array $transaction = [];

    /** The query that read the rows of the group */
    private string $selectQuery = '';

    public function testTheHostRowAndAServiceWithIdZeroGetDifferentKeys(): void
    {
        self::assertNotSame(centAclRowKey(5, null), centAclRowKey(5, 0));
        self::assertSame([5, null], centAclRowFromKey(centAclRowKey(5, null)));
        self::assertSame([5, 0], centAclRowFromKey(centAclRowKey(5, 0)));
        self::assertSame([5, 42], centAclRowFromKey(centAclRowKey(5, 42)));
    }

    public function testPackRowsKeepsTheKnownHostsOnlyWithTheirHostRowAndSorts(): void
    {
        $rows = centAclPackRows([7 => [3 => 1, 1 => 1], 99 => [4 => 1], 2 => []], [2 => 'h2', 7 => 'h7']);

        self::assertSame(
            [centAclRowKey(2, null), centAclRowKey(7, null), centAclRowKey(7, 1), centAclRowKey(7, 3)],
            $rows
        );
    }

    /**
     * @param list<int> $desired
     * @param list<int> $existing
     * @param list<int> $insert
     * @param list<int> $delete
     */
    #[DataProvider('provideDiffCases')]
    public function testDiffWritesOnlyTheRowsThatDiffer(array $desired, array $existing, array $insert, array $delete): void
    {
        self::assertSame(['insert' => $insert, 'delete' => $delete], centAclDiffRows($desired, $existing));
    }

    /**
     * @return iterable<string, array{list<int>, list<int>, list<int>, list<int>}>
     */
    public static function provideDiffCases(): iterable
    {
        yield 'nothing changed' => [[1, 2, 3], [1, 2, 3], [], []];
        yield 'empty table' => [[1, 2], [], [1, 2], []];
        yield 'no more rights' => [[], [1, 2], [], [1, 2]];
        yield 'rows added and removed' => [[1, 3, 4], [1, 2, 4, 5], [3], [2, 5]];
        // A duplicated host row is deleted, then inserted once.
        yield 'duplicated row still granted' => [[1, 2], [1, 1, 2], [1], [1]];
        yield 'duplicated row no longer granted' => [[2], [1, 1, 2], [], [1]];
    }

    public function testAGroupWhoseFingerprintDidNotChangeIsSkippedWithoutReadingOrWriting(): void
    {
        $rows = [centAclRowKey(1, null), centAclRowKey(1, 10)];
        $db = $this->createMock(CentreonDB::class);
        // The stored fingerprint, then: the group still has rows.
        $db->method('fetchOne')->willReturnOnConsecutiveCalls(centAclFingerprint($rows), 1);
        $db->expects(self::never())->method('prepare');
        $db->expects(self::never())->method('startTransaction');
        $db->expects(self::never())->method('executeStatement');
        $stats = $this->emptyStats();

        centAclWriteGroupRows($db, self::GROUP_ID, array_reverse($rows), true, $stats);

        self::assertSame(['groups' => 1, 'skipped' => 1, 'inserted' => 0, 'deleted' => 0], $stats);
    }

    public function testAGroupWhoseRowsWereDeletedOutsideTheCronIsRewrittenDespiteItsFingerprint(): void
    {
        // A support procedure empties centreon_acl before "Reload ACL": the fingerprints are still there.
        $rows = [centAclRowKey(1, null), centAclRowKey(1, 10)];
        $db = $this->storageDb(existing: [], fetchOneResults: [centAclFingerprint($rows), false]);
        $db->method('executeStatement')->willReturn(1);
        $stats = $this->emptyStats();

        centAclWriteGroupRows($db, self::GROUP_ID, $rows, true, $stats);

        self::assertSame([['insert', [1, null, self::GROUP_ID, 1, 10, self::GROUP_ID]]], $this->writes);
        self::assertSame(['groups' => 1, 'skipped' => 0, 'inserted' => 2, 'deleted' => 0], $stats);
    }

    public function testTheRowsOfTheGroupAreReadUnderLockInTheTransaction(): void
    {
        // A row another writer inserts meanwhile must wait for the commit: otherwise the fingerprint written by the
        // cron would overwrite the fingerprint removal of that writer.
        $db = $this->storageDb(existing: [[1, null]]);
        $db->method('executeStatement')->willReturn(1);
        $stats = $this->emptyStats();

        centAclWriteGroupRows($db, self::GROUP_ID, [centAclRowKey(1, null)], true, $stats);

        self::assertSame(['start', 'select', 'commit'], $this->transaction);
        self::assertStringEndsWith('FOR UPDATE', $this->selectQuery);
    }

    public function testOnlyTheChangedRowsAndTheFingerprintAreWrittenInOneTransaction(): void
    {
        // Existing: host 1 with service 10, host 2.
        $db = $this->storageDb(existing: [[1, null], [1, 10], [2, null]]);
        $fingerprints = [];
        $db->method('executeStatement')->willReturnCallback(
            function (string $query, ?QueryParameters $parameters) use (&$fingerprints): int {
                $this->transaction[] = 'fingerprint';
                $fingerprints[] = [$query, self::parameterValues($parameters)];

                return 1;
            }
        );
        $stats = $this->emptyStats();

        // Computed, with a duplicate: host 1 with services 10 and 11.
        $rows = [centAclRowKey(1, 11), centAclRowKey(1, null), centAclRowKey(1, 10), centAclRowKey(1, null)];
        centAclWriteGroupRows($db, self::GROUP_ID, $rows, true, $stats);

        self::assertSame(['start', 'select', 'delete', 'insert', 'fingerprint', 'commit'], $this->transaction);
        // Host 2 is no longer granted, service 11 of host 1 is new.
        self::assertSame([['delete', [self::GROUP_ID, 2]], ['insert', [1, 11, self::GROUP_ID]]], $this->writes);
        self::assertStringContainsString('centreon_acl_checksum', $fingerprints[0][0]);
        self::assertSame(
            [
                'groupId' => self::GROUP_ID,
                'fingerprint' => centAclFingerprint(centAclSortedUniqueRows($rows)),
            ],
            $fingerprints[0][1]
        );
        self::assertSame(['groups' => 1, 'skipped' => 0, 'inserted' => 1, 'deleted' => 1], $stats);
    }

    public function testAnErrorWhileWritingRollsBackSoTheGroupKeepsItsRowsAndFingerprint(): void
    {
        $db = $this->storageDb(existing: [[1, null]], failingInsert: true);
        $db->expects(self::never())->method('executeStatement');
        $stats = $this->emptyStats();

        try {
            centAclWriteGroupRows($db, self::GROUP_ID, [centAclRowKey(1, null), centAclRowKey(1, 10)], true, $stats);
            self::fail('The write error must be thrown');
        } catch (\PDOException) {
        }

        self::assertSame(['start', 'select', 'insert', 'rollback'], $this->transaction);
        self::assertSame(['groups' => 1, 'skipped' => 0, 'inserted' => 0, 'deleted' => 0], $stats);
    }

    public function testWithoutTheFingerprintTableTheGroupIsStillWrittenByDifference(): void
    {
        $db = $this->storageDb(existing: [[1, null]]);
        $db->expects(self::never())->method('fetchOne');
        $db->expects(self::never())->method('executeStatement');
        $stats = $this->emptyStats();

        centAclWriteGroupRows($db, self::GROUP_ID, [centAclRowKey(1, null), centAclRowKey(1, 10)], false, $stats);

        self::assertSame(['start', 'select', 'insert', 'commit'], $this->transaction);
        self::assertSame([['insert', [1, 10, self::GROUP_ID]]], $this->writes);
        self::assertSame(['groups' => 1, 'skipped' => 0, 'inserted' => 1, 'deleted' => 0], $stats);
    }

    public function testThePurgeRemovesTheRowsAndFingerprintsOfTheGroupsThatNoLongerGetRights(): void
    {
        $configurationDb = $this->createMock(CentreonDB::class);
        $configurationDb->method('fetchFirstColumn')->willReturn(['1', '2']);
        $storageDb = $this->createMock(CentreonDB::class);
        $storageDb->method('fetchFirstColumn')->willReturnCallback(
            static fn (string $query): array => str_contains($query, 'centreon_acl_checksum') ? ['1', '4'] : ['1', '2', '3']
        );
        $storageDb->method('isTransactionActive')->willReturn(false);
        $deleted = [];
        $storageDb->method('executeStatement')->willReturnCallback(
            static function (string $query, ?QueryParameters $parameters) use (&$deleted): int {
                $deleted[] = [
                    str_contains($query, 'centreon_acl_checksum') ? 'fingerprints' : 'rows',
                    array_values(self::parameterValues($parameters)),
                ];

                return 1;
            }
        );
        $storageDb->expects(self::once())->method('commitTransaction');

        self::assertSame(2, centAclPurgeStaleGroups($configurationDb, $storageDb, true));
        self::assertSame([['rows', [3, 4]], ['fingerprints', [3, 4]]], $deleted);
    }

    /**
     * @return array<string, mixed> parameter values by name
     */
    private static function parameterValues(?QueryParameters $parameters): array
    {
        $values = [];
        foreach ($parameters?->values() ?? [] as $parameter) {
            $values[$parameter->getName()] = $parameter->getValue();
        }

        return $values;
    }

    /**
     * @return array{groups: int, skipped: int, inserted: int, deleted: int}
     */
    private function emptyStats(): array
    {
        return ['groups' => 0, 'skipped' => 0, 'inserted' => 0, 'deleted' => 0];
    }

    /**
     * A storage connection holding the given rows for the group, which records the writes and the transaction.
     *
     * @param list<array{int, int|null}> $existing host id and service id (null for the host row)
     * @param list<mixed> $fetchOneResults successive results of fetchOne(): by default, no stored fingerprint
     */
    private function storageDb(
        array $existing,
        bool $failingInsert = false,
        array $fetchOneResults = [false],
    ): CentreonDB&MockObject {
        $select = $this->createMock(CentreonDBStatement::class);
        $select->method('fetch')->willReturnOnConsecutiveCalls(...[...$existing, false]);

        $db = $this->createMock(CentreonDB::class);
        $db->method('fetchOne')->willReturnOnConsecutiveCalls(...$fetchOneResults);
        $db->method('isTransactionActive')->willReturnCallback(
            fn (): bool => in_array('start', $this->transaction, true)
                && ! in_array('commit', $this->transaction, true)
                && ! in_array('rollback', $this->transaction, true)
        );
        $db->method('startTransaction')->willReturnCallback(function (): void {
            $this->transaction[] = 'start';
        });
        $db->method('commitTransaction')->willReturnCallback(function (): bool {
            $this->transaction[] = 'commit';

            return true;
        });
        $db->method('rollBackTransaction')->willReturnCallback(function (): bool {
            $this->transaction[] = 'rollback';

            return true;
        });
        $db->method('prepare')->willReturnCallback(
            function (string $query) use ($select, $failingInsert): CentreonDBStatement {
                if (str_starts_with($query, 'SELECT')) {
                    $this->transaction[] = 'select';
                    $this->selectQuery = $query;

                    return $select;
                }
                $kind = str_starts_with($query, 'DELETE') ? 'delete' : 'insert';
                $write = $this->createMock(CentreonDBStatement::class);
                $write->method('execute')->willReturnCallback(
                    function (?array $parameters = null) use ($kind, $failingInsert): bool {
                        $this->transaction[] = $kind;
                        if ($kind === 'insert' && $failingInsert) {
                            throw new \PDOException('Lost connection');
                        }
                        $this->writes[] = [$kind, $parameters ?? []];

                        return true;
                    }
                );

                return $write;
            }
        );

        return $db;
    }
}
