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

namespace Tests\www\api\class;

use CentreonCeipLicenseType;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../../../www/class/centreonCeipLicenseType.class.php';

final class CentreonCeipTest extends TestCase
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
                'unknownEdition' => null,
            ],
            CentreonCeipLicenseType::resolve($licenses),
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
        string $expectedLicenseType,
        bool $expectedFingerprint,
    ): void {
        self::assertSame(
            [
                'licenseType' => $expectedLicenseType,
                'withFingerprint' => $expectedFingerprint,
                'assetId' => null,
                'unknownEdition' => null,
            ],
            CentreonCeipLicenseType::resolve(['epp' => self::license(['edition' => $edition])]),
        );
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function declaredEditionProvider(): iterable
    {
        yield 'IT-100' => ['IT-100', 'IT-100 Edition', false];

        yield 'IT' => ['IT', 'IT Edition', false];

        yield 'BE' => ['BE', 'Business Edition', true];

        yield 'MSP' => ['MSP', 'MSP Edition', true];

        yield 'PRO' => ['PRO', 'PRO Edition', false];

        yield 'CUSTOM' => ['CUSTOM', 'Custom Edition', true];

        yield 'lowercase' => ['be', 'Business Edition', true];

        yield 'mixed case with spaces' => ['  Msp ', 'MSP Edition', true];
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
        $result = CentreonCeipLicenseType::resolve($licenses);

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
        $result = CentreonCeipLicenseType::resolve($licenses);

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
     */
    #[DataProvider('nonStandardEditionProvider')]
    public function testResolveReportsAnyNonStandardEditionAsCustomAndKeepsItsRawValue(
        array $licenses,
        string $expectedUnknownEdition,
    ): void {
        self::assertSame(
            [
                'licenseType' => 'Custom Edition',
                'withFingerprint' => true,
                'assetId' => null,
                'unknownEdition' => $expectedUnknownEdition,
            ],
            CentreonCeipLicenseType::resolve($licenses),
        );
    }

    /**
     * @return iterable<string, array{array<string, array<string, mixed>>, string}>
     */
    public static function nonStandardEditionProvider(): iterable
    {
        yield 'unknown value' => [['epp' => self::license(['edition' => 'ENTERPRISE'])], 'ENTERPRISE'];

        yield 'IT100 without hyphen' => [['epp' => self::license(['type' => 'IT100', 'edition' => 'IT100'])], 'IT100'];

        yield 'unknown value on an EPP computed as MSP' => [
            ['epp' => self::license([...self::unlimitedLicensing(), 'edition' => 'foo'])],
            'foo',
        ];
    }

    public function testResolveDoesNotFlagAnUnknownEditionWhenKnownEditionsCombineIntoCustom(): void
    {
        $result = CentreonCeipLicenseType::resolve([
            'epp' => self::license(['edition' => 'MSP']),
            'bam' => self::license(['edition' => 'BE']),
        ]);

        self::assertSame('Custom Edition', $result['licenseType']);
        self::assertNull($result['unknownEdition']);
    }

    /**
     * @param array<string, array<string, mixed>> $licenses
     */
    #[DataProvider('assetIdProvider')]
    public function testResolveReturnsTheAssetIdOfMspPlatformsOnly(array $licenses, ?string $expectedAssetId): void
    {
        self::assertSame($expectedAssetId, CentreonCeipLicenseType::resolve($licenses)['assetId']);
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
                'unknownEdition' => null,
            ],
            CentreonCeipLicenseType::resolve(['epp' => self::license(['edition' => $edition])]),
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
    public function testResolveThrowsOnAnInvalidLicenseDate(array $licensing): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid date format');

        CentreonCeipLicenseType::resolve(['epp' => self::license($licensing)]);
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
