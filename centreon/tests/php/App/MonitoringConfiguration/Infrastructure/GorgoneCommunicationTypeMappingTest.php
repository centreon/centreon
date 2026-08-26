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

namespace Tests\App\MonitoringConfiguration\Infrastructure;

use App\MonitoringConfiguration\Domain\Aggregate\Poller\GorgoneCommunicationTypeEnum;
use App\MonitoringConfiguration\Infrastructure\GorgoneCommunicationTypeMapping;
use App\MonitoringConfiguration\Infrastructure\InvalidGorgoneCommunicationTypeException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * 26.07 widened nagios_server.gorgone_communication_type to '1'..'4' and set every cloud poller
 * to '4' while the enum still stopped at SSH, so poller reads answered an HTTP 500 on cloud
 * platforms. The guards named testEveryCommunicationTypeAllowedBySchemaIsMapped,
 * testSchemaCommentDocumentsTheMappingItApplies and testTheLatestUpgradeScript* hold the column,
 * the comment that documents it, the upgrade scripts and the enum to each other.
 */
final class GorgoneCommunicationTypeMappingTest extends TestCase
{
    /**
     * The ALTER shapes the upgrade scripts actually use, in both families. It does not claim to
     * match every conceivable one: a CHANGE that renames another column into this one, or a
     * redeclaration to a type other than enum, would slip through.
     *
     * The capture runs to the next semicolon, so the COMMENT travels with the enum even when the
     * two sit on separate lines.
     */
    private const COLUMN_REDECLARATION_PATTERN = '/(?:ADD|MODIFY|CHANGE)(?:\\s+COLUMN)?\\s+'
        . '`?gorgone_communication_type`?\\s+(?:(?!enum\\b)`?\\w+`?\\s+)?enum\\s*\\([^)]*\\)[^;]*/i';

    /**
     * Update-next.php carries the release under development, so it outranks every numbered
     * script. Giving it a version above any Centreon release keeps the ordering a plain
     * version_compare rather than a special case threaded through the sort.
     */
    private const RELEASE_UNDER_DEVELOPMENT_VERSION = '99999';

    #[DataProvider('provideCommunicationTypes')]
    public function testFromDatabaseMapsEveryColumnValue(
        string $databaseValue,
        GorgoneCommunicationTypeEnum $expectedCommunicationType,
    ): void {
        self::assertSame(
            $expectedCommunicationType,
            GorgoneCommunicationTypeMapping::fromDatabase($databaseValue, 42)
        );
    }

    #[DataProvider('provideCommunicationTypes')]
    public function testToDatabaseMapsEveryCase(
        string $expectedDatabaseValue,
        GorgoneCommunicationTypeEnum $communicationType,
    ): void {
        self::assertSame($expectedDatabaseValue, GorgoneCommunicationTypeMapping::toDatabase($communicationType));
    }

    /**
     * @return iterable<string, array{string, GorgoneCommunicationTypeEnum}>
     */
    public static function provideCommunicationTypes(): iterable
    {
        yield 'zmq' => ['1', GorgoneCommunicationTypeEnum::ZMQ];

        yield 'ssh' => ['2', GorgoneCommunicationTypeEnum::SSH];

        yield 'pull' => ['3', GorgoneCommunicationTypeEnum::Pull];

        yield 'pullwss' => ['4', GorgoneCommunicationTypeEnum::PullWss];
    }

    /**
     * This test cannot see an inversion: a round trip through a bijection and its inverse is the
     * identity, so relabelling both directions consistently survives it. The table of
     * expectations above is what catches that, and it alone.
     *
     * What this adds is totality over two sets the hand-written table does not derive from —
     * GorgoneCommunicationTypeEnum::cases() and the values createTables.sql allows. A case added
     * on one side only fails here, and nowhere else.
     */
    public function testTheTwoDirectionsAreReciprocal(): void
    {
        foreach (GorgoneCommunicationTypeEnum::cases() as $communicationType) {
            self::assertSame(
                $communicationType,
                GorgoneCommunicationTypeMapping::fromDatabase(
                    GorgoneCommunicationTypeMapping::toDatabase($communicationType),
                    42
                ),
                "writing {$communicationType->name} then reading it back does not give it back"
            );
        }

        foreach ($this->communicationTypesAllowedBySchema() as $databaseValue) {
            self::assertSame(
                $databaseValue,
                GorgoneCommunicationTypeMapping::toDatabase(
                    GorgoneCommunicationTypeMapping::fromDatabase($databaseValue, 42)
                ),
                "reading '{$databaseValue}' then writing it back does not give it back"
            );
        }
    }

    #[DataProvider('provideUnmappableValues')]
    public function testFromDatabaseRejectsUnmappableValues(string $databaseValue): void
    {
        $this->expectException(InvalidGorgoneCommunicationTypeException::class);
        $this->expectExceptionMessage(sprintf('"%s" read from the database for poller #42', $databaseValue));

        GorgoneCommunicationTypeMapping::fromDatabase($databaseValue, 42);
    }

    public function testTheRejectionCarriesItsContextAsData(): void
    {
        try {
            GorgoneCommunicationTypeMapping::fromDatabase('', 42);
            self::fail('an unmappable value was accepted');
        } catch (InvalidGorgoneCommunicationTypeException $exception) {
            self::assertSame('', $exception->value);
            self::assertSame(42, $exception->pollerId);
        }
    }

    /**
     * '5' and '0' cannot occur in a column declared enum('1','2','3','4'); '' can, because
     * without strict mode MySQL stores the empty error member instead of rejecting an
     * out-of-enum write. An ALTER that narrows the enum over existing rows produces exactly
     * that, which is what testNoUpgradeScriptWidensBeyondAFreshInstall watches for.
     *
     * @return iterable<string, array{string}>
     */
    public static function provideUnmappableValues(): iterable
    {
        yield 'out of range' => ['5'];

        yield 'zero' => ['0'];

        yield 'mysql error member' => [''];
    }

    public function testEveryCommunicationTypeAllowedBySchemaIsMapped(): void
    {
        $mappedCases = [];
        foreach ($this->communicationTypesAllowedBySchema() as $databaseValue) {
            try {
                $mappedCases[] = GorgoneCommunicationTypeMapping::fromDatabase($databaseValue, 42);
            } catch (\Throwable $throwable) {
                self::fail(
                    "createTables.sql allows gorgone_communication_type '{$databaseValue}' but the "
                    . "mapping rejects it: {$throwable->getMessage()}"
                );
            }
        }

        self::assertEqualsCanonicalizing(
            GorgoneCommunicationTypeEnum::cases(),
            $mappedCases,
            'nagios_server.gorgone_communication_type and GorgoneCommunicationTypeEnum have drifted apart'
        );
    }

    /**
     * Comparing the two sets cannot see two values swapped, and a wrong comment is exactly what
     * shipped for a whole release: createTables.sql and Update-26.07.0.php both documented
     * '1: SSH, 2: ZMQ' while the mapping has always read '1' as ZMQ. The comparison is
     * case-insensitive because the comment spells the protocol PullWSS while the case is PullWss.
     */
    public function testSchemaCommentDocumentsTheMappingItApplies(): void
    {
        foreach ($this->communicationTypesDocumentedBySchema() as $databaseValue => $documentedName) {
            $communicationType = GorgoneCommunicationTypeMapping::fromDatabase((string) $databaseValue, 42);

            self::assertSame(
                mb_strtolower($documentedName),
                mb_strtolower($communicationType->name),
                "createTables.sql documents '{$databaseValue}: {$documentedName}' but the mapping "
                . "reads that value as {$communicationType->name}"
            );
        }
    }

    /**
     * Upgraded platforms get their column from an upgrade script rather than from
     * createTables.sql. No script may allow a value a fresh install rejects, which would leave
     * rows the mapping cannot read. Shipped scripts legitimately allow fewer values than today's
     * schema — they were written when the enum was shorter — so only widening is a defect here.
     */
    public function testNoUpgradeScriptWidensBeyondAFreshInstall(): void
    {
        $allowedByFreshInstall = $this->communicationTypesAllowedBySchema();

        foreach ($this->columnRedeclarationsByUpgradeScript() as $redeclaration) {
            $allowedByUpgrade = $this->enumValues($redeclaration['definition']);

            self::assertSame(
                [],
                array_values(array_diff($allowedByUpgrade, $allowedByFreshInstall)),
                "{$redeclaration['script']} widens gorgone_communication_type beyond "
                . 'createTables.sql, so upgraded platforms would hold values a fresh install '
                . 'never produces'
            );
        }
    }

    /**
     * The last script to redeclare the column is the one an upgraded platform ends up with, so it
     * has to land on exactly the schema a fresh install produces. Narrowing matters here and not
     * on the scripts it supersedes: it would leave existing rows outside the enum, which is how
     * MySQL ends up storing the empty error member.
     */
    public function testTheLatestUpgradeScriptLandsOnTheFreshInstallSchema(): void
    {
        ['script' => $script, 'definition' => $definition] = $this->latestColumnRedeclaration();

        self::assertEqualsCanonicalizing(
            $this->communicationTypesAllowedBySchema(),
            $this->enumValues($definition),
            "{$script} and createTables.sql do not allow the same gorgone_communication_type "
            . 'values, so upgraded and freshly installed platforms would disagree'
        );

        // The allowed values alone are not "exactly the schema": the legacy poller form omits
        // the column when the field is absent, so a DEFAULT that drifted would silently label
        // the protocol of pollers created on upgraded platforms.
        self::assertSame(
            $this->columnConstraints($this->columnDefinitionFromSchema()),
            $this->columnConstraints($definition),
            "{$script} and createTables.sql disagree on the nullability or the default of "
            . 'gorgone_communication_type'
        );
    }

    /**
     * The comment shipped to upgraded platforms is what an operator reads with SHOW FULL COLUMNS,
     * and an inverted one is the defect this guard exists for. Superseded scripts are deliberately
     * left alone — Update-26.07.0.php still carries the inverted comment and is corrected forward
     * rather than rewritten — so only the last redeclaration is held to the mapping.
     */
    public function testTheLatestUpgradeScriptDocumentsTheMappingItApplies(): void
    {
        ['script' => $script, 'definition' => $definition] = $this->latestColumnRedeclaration();

        self::assertSame(
            1,
            preg_match("/COMMENT '([^']+)'/i", $definition, $comment),
            "{$script} redeclares gorgone_communication_type without documenting its values"
        );

        self::assertGreaterThanOrEqual(
            1,
            preg_match_all('/(\d+)\s*:\s*(\w+)/', $comment[1], $documentedPairs, PREG_SET_ORDER),
            "unreadable column comment in {$script}: {$comment[1]}"
        );

        self::assertEqualsCanonicalizing(
            $this->enumValues($definition),
            array_column($documentedPairs, 1),
            "{$script} redeclares gorgone_communication_type without documenting every value it "
            . 'allows'
        );

        foreach ($documentedPairs as $documentedPair) {
            $communicationType = GorgoneCommunicationTypeMapping::fromDatabase($documentedPair[1], 42);

            self::assertSame(
                mb_strtolower($documentedPair[2]),
                mb_strtolower($communicationType->name),
                "{$script} documents '{$documentedPair[1]}: {$documentedPair[2]}' but the "
                . "mapping reads that value as {$communicationType->name}"
            );
        }
    }

    /**
     * Naming Update-next.php here would guard nothing one release from now: the release chore
     * renames it to Update-<version>.php and resets Update-next.php from its template, so
     * testTheLatestUpgradeScriptLandsOnTheFreshInstallSchema and
     * testTheLatestUpgradeScriptDocumentsTheMappingItApplies would read an empty skeleton and go
     * green. What has to match the mapping is the last script to redeclare the column, whichever
     * file that turns out to be.
     *
     * @return array{script: string, version: string, family: int, definition: string}
     */
    private function latestColumnRedeclaration(): array
    {
        $redeclarations = $this->columnRedeclarationsByUpgradeScript();

        // Same version, two families: the platform runs the php script before the configuration
        // SQL, so 'family' breaks the tie explicitly rather than leaving it to the order the two
        // globs happen to be merged in. usort is stable since PHP 8.0, so several ALTERs within
        // one script keep their execution order and the last one applied wins.
        usort(
            $redeclarations,
            static fn (array $left, array $right): int => version_compare($left['version'], $right['version'])
                ?: $left['family'] <=> $right['family']
        );

        return end($redeclarations);
    }

    /**
     * @return string a version_compare-able rank: the release the script upgrades to, or a
     *                sentinel above every release for the one under development
     */
    private function upgradeScriptVersion(string $script): string
    {
        if (preg_match('/^Update-(?:DB-)?next\./i', $script) === 1) {
            return self::RELEASE_UNDER_DEVELOPMENT_VERSION;
        }

        self::assertSame(
            1,
            preg_match('/^Update-(?:DB-)?(.+)\.(?:php|sql)$/i', $script, $version),
            "cannot tell which release {$script} upgrades to, so it cannot be ordered against the "
            . 'other upgrade scripts'
        );

        return $version[1];
    }

    /**
     * @return array{nullability: string, default: string|null} the parts of a column definition
     *                                                          that are not its allowed values
     */
    private function columnConstraints(string $definition): array
    {
        return [
            'nullability' => preg_match('/\bNOT\s+NULL\b/i', $definition) === 1 ? 'NOT NULL' : 'NULL',
            'default' => preg_match("/\bDEFAULT\s+'([^']*)'/i", $definition, $default) === 1
                ? $default[1]
                : null,
        ];
    }

    /**
     * @return list<string>
     */
    private function communicationTypesAllowedBySchema(): array
    {
        return $this->enumValues($this->columnDefinitionFromSchema());
    }

    /**
     * @return array<array-key, string> database value => case name documented by the column comment
     */
    private function communicationTypesDocumentedBySchema(): array
    {
        $definition = $this->columnDefinitionFromSchema();

        self::assertSame(
            1,
            preg_match("/COMMENT '([^']+)'/i", $definition, $comment),
            'the gorgone_communication_type column of createTables.sql no longer documents its '
            . 'values on the same line as its definition'
        );

        self::assertGreaterThanOrEqual(
            1,
            preg_match_all('/(\d+)\s*:\s*(\w+)/', $comment[1], $documentedPairs, PREG_SET_ORDER),
            "unreadable column comment: {$comment[1]}"
        );

        $documented = [];
        foreach ($documentedPairs as $documentedPair) {
            $documented[$documentedPair[1]] = $documentedPair[2];
        }

        // Checking the pairs that ARE documented would pass on a comment that documents one
        // value and forgets the rest — including the 1/2 pair this whole file exists for.
        self::assertEqualsCanonicalizing(
            $this->enumValues($definition),
            array_map(strval(...), array_keys($documented)),
            'the gorgone_communication_type comment in createTables.sql does not document every '
            . 'value the column allows'
        );

        return $documented;
    }

    /**
     * @return non-empty-list<array{script: string, version: string, family: int, definition: string}>
     */
    private function columnRedeclarationsByUpgradeScript(): array
    {
        $scripts = array_merge(
            glob(dirname(__DIR__, 5) . '/www/install/php/Update-*.php') ?: [],
            glob(dirname(__DIR__, 5) . '/www/install/sql/centreon/Update-DB-*.sql') ?: [],
        );
        self::assertNotSame([], $scripts, 'no upgrade script found, the glob no longer resolves');

        $redeclarations = [];
        foreach ($scripts as $script) {
            $matched = preg_match_all(
                self::COLUMN_REDECLARATION_PATTERN,
                (string) file_get_contents($script),
                $definitions,
                PREG_SET_ORDER
            );
            for ($index = 0; $index < $matched; $index++) {
                $redeclarations[] = [
                    'script' => basename($script) . ($index === 0 ? '' : " (ALTER #{$index})"),
                    'version' => $this->upgradeScriptVersion(basename($script)),
                    'family' => str_ends_with($script, '.sql') ? 1 : 0,
                    'definition' => $definitions[$index][0],
                ];
            }
        }

        self::assertNotSame(
            [],
            $redeclarations,
            'no upgrade script redeclares gorgone_communication_type any more, this guard guards nothing'
        );

        return $redeclarations;
    }

    /**
     * `[^;]*?` rather than `.*?` keeps the search inside the CREATE TABLE: with /s a dot would
     * cross the statement's terminating semicolon and find a same-named column in any later
     * table, so dropping the column from nagios_server would go unnoticed.
     *
     * The capture stops at the end of the line, so the COMMENT has to sit on the same line as the
     * column definition. That is how createTables.sql is written throughout; a reformat that
     * breaks the line would fail this guard rather than the change under test.
     */
    private function columnDefinitionFromSchema(): string
    {
        $schemaPath = dirname(__DIR__, 5) . '/www/install/createTables.sql';
        self::assertFileExists($schemaPath, "createTables.sql not found at {$schemaPath}");

        self::assertSame(
            1,
            preg_match(
                '/CREATE TABLE `nagios_server` \([^;]*?(`?gorgone_communication_type`?\s+enum\s*\([^)]*\)[^\n]*)/is',
                (string) file_get_contents($schemaPath),
                $column
            ),
            'the gorgone_communication_type column is no longer parsable in the nagios_server table'
        );

        return $column[1];
    }

    /**
     * @return list<string>
     */
    private function enumValues(string $definition): array
    {
        self::assertSame(
            1,
            preg_match('/enum\s*\(([^)]*)\)/i', $definition, $enum),
            "no enum definition found in: {$definition}"
        );

        self::assertGreaterThanOrEqual(
            1,
            preg_match_all("/'([^']*)'/", $enum[1], $values),
            "no quoted value found in: {$enum[1]}"
        );

        return $values[1];
    }
}
