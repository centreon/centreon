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

namespace Tests\www\class;

use CentreonCeipLicenseType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../../www/class/centreonCeipLicenseType.class.php';

final class CentreonCeipLicenseTypeTest extends TestCase
{
    /**
     * @param array<string, array<string, mixed>> $licenses
     */
    #[DataProvider('licensesWithoutEditionProvider')]
    public function testResolveComputesTheLicenseTypeWhenNoLicenseCarriesAnEdition(
        array $licenses,
        string $expectedLicenseType,
        bool $expectedFingerprint,
    ): void {
        self::assertSame(
            [
                'licenseType' => $expectedLicenseType,
                'withFingerprint' => $expectedFingerprint,
                'assetId' => null,
                'unknownEditions' => [],
                'licensesWithInvalidDate' => [],
            ],
            CentreonCeipLicenseType::resolve($licenses, false),
        );
    }

    /**
     * @return iterable<string, array{array<string, array<string, mixed>>, string, bool}>
     */
    public static function licensesWithoutEditionProvider(): iterable
    {
        yield 'EPP' => [['epp' => self::license()], 'IT Edition', false];

        yield 'EPP IT100' => [['epp' => self::license(['type' => 'IT100'])], 'IT-100 Edition', false];

        yield 'EPP unlimited for more than 90 days' => [
            ['epp' => self::license(self::unlimitedLicensing())],
            'MSP Edition',
            true,
        ];

        yield 'EPP unlimited for 90 days or less' => [
            ['epp' => self::license(['hosts' => -1, 'start' => '2026-01-01', 'end' => '2026-03-01'])],
            'IT Edition',
            false,
        ];

        yield 'EPP and BAM' => [['epp' => self::license(), 'bam' => self::license()], 'Business Edition', true];

        yield 'IT100 EPP and MAP' => [
            ['epp' => self::license(['type' => 'IT100']), 'map' => self::license()],
            'Business Edition',
            true,
        ];

        yield 'standalone MBI' => [['mbi' => self::license()], 'Business Edition', true];

        yield 'unlimited BAM for more than 90 days' => [
            ['bam' => self::license(self::unlimitedLicensing())],
            'MSP Edition',
            true,
        ];

        yield 'no license' => [[], 'Open Source', false];
    }

    #[DataProvider('declaredEditionProvider')]
    public function testResolveUsesTheDeclaredEditionAsLicenseType(
        string $edition,
        bool $isCloudPlatform,
        string $expectedLicenseType,
        bool $expectedFingerprint,
    ): void {
        self::assertSame(
            [
                'licenseType' => $expectedLicenseType,
                'withFingerprint' => $expectedFingerprint,
                'assetId' => null,
                'unknownEditions' => [],
                'licensesWithInvalidDate' => [],
            ],
            CentreonCeipLicenseType::resolve(['epp' => self::license(['edition' => $edition])], $isCloudPlatform),
        );
    }

    /**
     * @return iterable<string, array{string, bool, string, bool}>
     */
    public static function declaredEditionProvider(): iterable
    {
        yield 'IT-100' => ['IT-100', false, 'IT-100 Edition', false];

        yield 'IT' => ['IT', false, 'IT Edition', false];

        yield 'BE' => ['BE', false, 'Business Edition', true];

        yield 'MSP' => ['MSP', false, 'MSP Edition', true];

        yield 'PRO on Cloud' => ['PRO', true, 'PRO Edition', false];

        yield 'CUSTOM' => ['CUSTOM', false, 'Custom Edition', true];

        yield 'lowercase' => ['be', false, 'Business Edition', true];

        yield 'mixed case with spaces' => ['  Msp ', false, 'MSP Edition', true];

        yield 'IT on Cloud' => ['IT', true, 'IT Edition', false];
    }

    /**
     * @param array<string, array<string, mixed>> $licenses
     */
    #[DataProvider('editionOverridingComputedTypeProvider')]
    public function testResolveMakesTheFingerprintFollowTheDeclaredEditionRatherThanTheComputedType(
        array $licenses,
        string $expectedLicenseType,
        bool $expectedFingerprint,
    ): void {
        $result = CentreonCeipLicenseType::resolve($licenses, false);

        self::assertSame($expectedLicenseType, $result['licenseType']);
        self::assertSame($expectedFingerprint, $result['withFingerprint']);
    }

    /**
     * @return iterable<string, array{array<string, array<string, mixed>>, string, bool}>
     */
    public static function editionOverridingComputedTypeProvider(): iterable
    {
        yield 'IT edition on an EPP computed as MSP' => [
            ['epp' => self::license([...self::unlimitedLicensing(), 'edition' => 'IT'])],
            'IT Edition',
            false,
        ];

        yield 'BE edition on an EPP computed as IT' => [
            ['epp' => self::license(['edition' => 'BE'])],
            'Business Edition',
            true,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $licenses
     */
    #[DataProvider('combinedEditionsProvider')]
    public function testResolveCombinesTheEditionsDeclaredByEveryLicense(
        array $licenses,
        string $expectedLicenseType,
        bool $expectedFingerprint,
    ): void {
        $result = CentreonCeipLicenseType::resolve($licenses, false);

        self::assertSame($expectedLicenseType, $result['licenseType']);
        self::assertSame($expectedFingerprint, $result['withFingerprint']);
    }

    /**
     * @return iterable<string, array{array<string, array<string, mixed>>, string, bool}>
     */
    public static function combinedEditionsProvider(): iterable
    {
        yield 'IT EPP and CUSTOM BAM' => [
            ['epp' => self::license(['edition' => 'IT']), 'bam' => self::license(['edition' => 'CUSTOM'])],
            'Custom Edition',
            true,
        ];

        yield 'IT-100 EPP and CUSTOM MAP' => [
            ['epp' => self::license(['edition' => 'IT-100']), 'map' => self::license(['edition' => 'CUSTOM'])],
            'Custom Edition',
            true,
        ];

        yield 'BE EPP and BE BAM' => [
            ['epp' => self::license(['edition' => 'BE']), 'bam' => self::license(['edition' => 'be'])],
            'Business Edition',
            true,
        ];

        yield 'MSP EPP and BE BAM' => [
            ['epp' => self::license(['edition' => 'MSP']), 'bam' => self::license(['edition' => 'BE'])],
            'Custom Edition',
            true,
        ];

        yield 'EPP without edition and CUSTOM BAM' => [
            ['epp' => self::license(), 'bam' => self::license(['edition' => 'CUSTOM'])],
            'Custom Edition',
            true,
        ];

        yield 'IT EPP and BAM without edition' => [
            ['epp' => self::license(['edition' => 'IT']), 'bam' => self::license()],
            'Custom Edition',
            true,
        ];

        yield 'IT-100 EPP and MBI without edition' => [
            ['epp' => self::license(['edition' => 'IT-100']), 'mbi' => self::license()],
            'Custom Edition',
            true,
        ];

        yield 'PRO EPP and PRO MAP' => [
            ['epp' => self::license(['edition' => 'PRO']), 'map' => self::license(['edition' => 'PRO'])],
            'Custom Edition',
            true,
        ];

        yield 'BE EPP and BAM without edition' => [
            ['epp' => self::license(['edition' => 'BE']), 'bam' => self::license()],
            'Business Edition',
            true,
        ];

        yield 'MSP EPP and MAP without edition' => [
            ['epp' => self::license([...self::unlimitedLicensing(), 'edition' => 'MSP']), 'map' => self::license()],
            'MSP Edition',
            true,
        ];

        yield 'standalone CUSTOM MBI' => [['mbi' => self::license(['edition' => 'CUSTOM'])], 'Custom Edition', true];
    }

    /**
     * @param array<string, array<string, mixed>> $licenses
     * @param list<string> $expectedUnknownEditions
     */
    #[DataProvider('nonStandardEditionProvider')]
    public function testResolveReportsAnyNonStandardEditionAsCustomAndKeepsItsRawValue(
        array $licenses,
        array $expectedUnknownEditions,
    ): void {
        self::assertSame(
            [
                'licenseType' => 'Custom Edition',
                'withFingerprint' => true,
                'assetId' => null,
                'unknownEditions' => $expectedUnknownEditions,
                'licensesWithInvalidDate' => [],
            ],
            CentreonCeipLicenseType::resolve($licenses, false),
        );
    }

    /**
     * @return iterable<string, array{array<string, array<string, mixed>>, list<string>}>
     */
    public static function nonStandardEditionProvider(): iterable
    {
        yield 'unknown value' => [['epp' => self::license(['edition' => 'ENTERPRISE'])], ['ENTERPRISE']];

        yield 'IT100 without hyphen' => [['epp' => self::license(['type' => 'IT100', 'edition' => 'IT100'])], ['IT100']];

        yield 'unknown value on an EPP computed as MSP' => [
            ['epp' => self::license([...self::unlimitedLicensing(), 'edition' => 'foo'])],
            ['foo'],
        ];

        yield 'unknown value combined with a known one' => [
            ['epp' => self::license(['edition' => 'ENTERPRISE']), 'bam' => self::license(['edition' => 'BE'])],
            ['ENTERPRISE'],
        ];

        yield 'several unknown values' => [
            ['epp' => self::license(['edition' => 'foo']), 'bam' => self::license(['edition' => 'bar'])],
            ['foo', 'bar'],
        ];

        yield 'Cloud-only PRO on an on-premise platform' => [['epp' => self::license(['edition' => 'Pro'])], ['Pro']];
    }

    public function testResolveDoesNotFlagAnUnknownEditionWhenKnownEditionsCombineIntoCustom(): void
    {
        $result = CentreonCeipLicenseType::resolve([
            'epp' => self::license(['edition' => 'MSP']),
            'bam' => self::license(['edition' => 'BE']),
        ], false);

        self::assertSame('Custom Edition', $result['licenseType']);
        self::assertSame([], $result['unknownEditions']);
    }

    /**
     * @param array<string, array<string, mixed>> $licenses
     */
    #[DataProvider('assetIdProvider')]
    public function testResolveReturnsTheAssetIdOfMspPlatformsOnly(array $licenses, ?string $expectedAssetId): void
    {
        self::assertSame($expectedAssetId, CentreonCeipLicenseType::resolve($licenses, false)['assetId']);
    }

    /**
     * @return iterable<string, array{array<string, array<string, mixed>>, string|null}>
     */
    public static function assetIdProvider(): iterable
    {
        yield 'MSP edition' => [
            ['epp' => self::license(['edition' => 'MSP', 'asset_id' => ' 02i5800000ABCDE '])],
            '02i5800000ABCDE',
        ];

        yield 'EPP computed as MSP' => [['epp' => self::license([...self::unlimitedLicensing(), 'asset_id' => 'A1'])], 'A1'];

        yield 'BAM computed as MSP' => [
            ['epp' => self::license(), 'bam' => self::license([...self::unlimitedLicensing(), 'asset_id' => 'B1'])],
            'B1',
        ];

        yield 'MSP edition without asset ID' => [['epp' => self::license(['edition' => 'MSP'])], null];

        yield 'MSP edition with an empty asset ID' => [['epp' => self::license(['edition' => 'MSP', 'asset_id' => ''])], null];

        yield 'BE edition' => [['epp' => self::license(['edition' => 'BE', 'asset_id' => 'A1'])], null];

        yield 'IT edition on an EPP computed as MSP' => [
            ['epp' => self::license([...self::unlimitedLicensing(), 'edition' => 'IT', 'asset_id' => 'A1'])],
            null,
        ];

        yield 'custom edition' => [['epp' => self::license(['edition' => 'foo', 'asset_id' => 'A1'])], null];
    }

    #[DataProvider('ignoredEditionProvider')]
    public function testResolveIgnoresAnEmptyOrNonStringEdition(mixed $edition): void
    {
        self::assertSame(
            [
                'licenseType' => 'IT Edition',
                'withFingerprint' => false,
                'assetId' => null,
                'unknownEditions' => [],
                'licensesWithInvalidDate' => [],
            ],
            CentreonCeipLicenseType::resolve(['epp' => self::license(['edition' => $edition])], false),
        );
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function ignoredEditionProvider(): iterable
    {
        yield 'empty string' => [''];

        yield 'blank string' => ['   '];

        yield 'null' => [null];

        yield 'integer' => [42];
    }

    /**
     * @param array<string, mixed> $licensing
     */
    #[DataProvider('invalidDateProvider')]
    public function testResolveSkipsALicenseWithAnInvalidDate(array $licensing): void
    {
        self::assertSame(
            [
                'licenseType' => 'Open Source',
                'withFingerprint' => false,
                'assetId' => null,
                'unknownEditions' => [],
                'licensesWithInvalidDate' => ['epp'],
            ],
            CentreonCeipLicenseType::resolve(['epp' => self::license([...$licensing, 'edition' => 'BE'])], false),
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidDateProvider(): iterable
    {
        yield 'unparseable end' => [['end' => 'not-a-date']];

        yield 'unparseable start' => [['start' => 'not-a-date']];

        yield 'invalid calendar end' => [['end' => '2025-02-30']];

        yield 'invalid calendar start' => [['start' => '2025-02-30']];

        yield 'missing end' => [['end' => null]];
    }

    /**
     * @param array<string, array<string, mixed>> $licenses
     * @param list<string> $expectedLicensesWithInvalidDate
     */
    #[DataProvider('otherLicenseWithInvalidDateProvider')]
    public function testResolveKeepsTheOtherLicensesWhenOneHasAnInvalidDate(
        array $licenses,
        string $expectedLicenseType,
        bool $expectedFingerprint,
        array $expectedLicensesWithInvalidDate,
    ): void {
        $result = CentreonCeipLicenseType::resolve($licenses, false);

        self::assertSame($expectedLicenseType, $result['licenseType']);
        self::assertSame($expectedFingerprint, $result['withFingerprint']);
        self::assertSame($expectedLicensesWithInvalidDate, $result['licensesWithInvalidDate']);
    }

    /**
     * @return iterable<string, array{array<string, array<string, mixed>>, string, bool, list<string>}>
     */
    public static function otherLicenseWithInvalidDateProvider(): iterable
    {
        yield 'valid EPP and BAM with an invalid date' => [
            ['epp' => self::license(), 'bam' => self::license(['end' => '2025-02-30'])],
            'IT Edition',
            false,
            ['bam'],
        ];

        yield 'IT EPP and CUSTOM BAM with an invalid date' => [
            [
                'epp' => self::license(['edition' => 'IT']),
                'bam' => self::license(['edition' => 'CUSTOM', 'start' => 'not-a-date']),
            ],
            'IT Edition',
            false,
            ['bam'],
        ];

        yield 'EPP with an invalid date and valid MAP' => [
            ['epp' => self::license(['end' => 'not-a-date']), 'map' => self::license()],
            'Business Edition',
            true,
            ['epp'],
        ];
    }

    /**
     * @param array<string, mixed> $licensing
     *
     * @return array<string, mixed>
     */
    private static function license(array $licensing = []): array
    {
        return [
            'client' => ['name' => 'ACME'],
            'platform' => ['environment' => 'production'],
            'licensing' => [
                'hosts' => 50,
                'type' => 'IT',
                'start' => '2026-01-01',
                'end' => '2026-02-01',
                ...$licensing,
            ],
        ];
    }

    /**
     * @return array<string, mixed> unlimited hosts for one year
     */
    private static function unlimitedLicensing(): array
    {
        return ['hosts' => -1, 'start' => '2026-01-01', 'end' => '2027-01-01'];
    }
}
