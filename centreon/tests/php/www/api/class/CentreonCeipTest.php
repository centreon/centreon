<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

require_once __DIR__ . '/../../../../../www/class/centreonCeipLicenseType.class.php';

/**
 * @param array<string, mixed> $licensing
 *
 * @return array<string, mixed>
 */
function ceipLicense(array $licensing = []): array
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

/** @return array<string, mixed> unlimited hosts for one year */
function ceipUnlimitedLicensing(): array
{
    return ['hosts' => -1, 'start' => '2026-01-01', 'end' => '2027-01-01'];
}

it('computes the license type when no license carries an edition', function (
    array $licenses,
    string $expectedLicenseType,
    bool $expectedFingerprint,
): void {
    expect(CentreonCeipLicenseType::resolve($licenses))->toBe([
        'licenseType' => $expectedLicenseType,
        'withFingerprint' => $expectedFingerprint,
        'ignoredEdition' => null,
    ]);
})->with([
    'EPP' => [['epp' => ceipLicense()], 'IT Edition', false],
    'EPP IT100' => [['epp' => ceipLicense(['type' => 'IT100'])], 'IT-100 Edition', false],
    'EPP unlimited for more than 90 days' => [['epp' => ceipLicense(ceipUnlimitedLicensing())], 'MSP Edition', true],
    'EPP unlimited for 90 days or less' => [
        ['epp' => ceipLicense(['hosts' => -1, 'start' => '2026-01-01', 'end' => '2026-03-01'])],
        'IT Edition',
        false,
    ],
    'EPP and BAM' => [['epp' => ceipLicense(), 'bam' => ceipLicense()], 'Business Edition', true],
    'IT100 EPP and MAP' => [['epp' => ceipLicense(['type' => 'IT100']), 'map' => ceipLicense()], 'Business Edition', true],
    'standalone MBI' => [['mbi' => ceipLicense()], 'Business Edition', true],
    'unlimited BAM for more than 90 days' => [['bam' => ceipLicense(ceipUnlimitedLicensing())], 'MSP Edition', true],
    'no license' => [[], 'Open Source', false],
]);

it('uses the declared edition as license type', function (
    string $edition,
    string $expectedLicenseType,
    bool $expectedFingerprint,
): void {
    expect(CentreonCeipLicenseType::resolve(['epp' => ceipLicense(['edition' => $edition])]))->toBe([
        'licenseType' => $expectedLicenseType,
        'withFingerprint' => $expectedFingerprint,
        'ignoredEdition' => null,
    ]);
})->with([
    'IT-100' => ['IT-100', 'IT-100 Edition', false],
    'IT' => ['IT', 'IT Edition', false],
    'BE' => ['BE', 'Business Edition', true],
    'MSP' => ['MSP', 'MSP Edition', true],
    'PRO' => ['PRO', 'PRO Edition', false],
    'CUSTOM' => ['CUSTOM', 'Custom Edition', true],
    'lowercase' => ['be', 'Business Edition', true],
    'mixed case with spaces' => ['  Msp ', 'MSP Edition', true],
]);

it('makes the fingerprint follow the declared edition rather than the computed type', function (
    array $licenses,
    string $expectedLicenseType,
    bool $expectedFingerprint,
): void {
    $result = CentreonCeipLicenseType::resolve($licenses);

    expect($result['licenseType'])->toBe($expectedLicenseType)
        ->and($result['withFingerprint'])->toBe($expectedFingerprint);
})->with([
    'IT edition on an EPP computed as MSP' => [
        ['epp' => ceipLicense([...ceipUnlimitedLicensing(), 'edition' => 'IT'])],
        'IT Edition',
        false,
    ],
    'IT edition with a BAM license' => [
        ['epp' => ceipLicense(['edition' => 'IT']), 'bam' => ceipLicense()],
        'IT Edition',
        false,
    ],
    'BE edition on an EPP computed as IT' => [
        ['epp' => ceipLicense(['edition' => 'BE'])],
        'Business Edition',
        true,
    ],
]);

it('keeps the first declared edition across licenses', function (): void {
    $result = CentreonCeipLicenseType::resolve([
        'epp' => ceipLicense(),
        'bam' => ceipLicense(['edition' => 'CUSTOM']),
    ]);

    expect($result['licenseType'])->toBe('Custom Edition')
        ->and($result['withFingerprint'])->toBeTrue();
});

it('ignores an unknown edition and keeps the computed license type', function (
    array $licenses,
    string $expectedLicenseType,
    bool $expectedFingerprint,
    string $expectedIgnoredEdition,
): void {
    expect(CentreonCeipLicenseType::resolve($licenses))->toBe([
        'licenseType' => $expectedLicenseType,
        'withFingerprint' => $expectedFingerprint,
        'ignoredEdition' => $expectedIgnoredEdition,
    ]);
})->with([
    'unknown value' => [['epp' => ceipLicense(['edition' => 'ENTERPRISE'])], 'IT Edition', false, 'ENTERPRISE'],
    'IT100 without hyphen' => [['epp' => ceipLicense(['type' => 'IT100', 'edition' => 'IT100'])], 'IT-100 Edition', false, 'IT100'],
    'unknown value with a BAM license' => [
        ['epp' => ceipLicense(['edition' => 'foo']), 'bam' => ceipLicense()],
        'Business Edition',
        true,
        'FOO',
    ],
]);

it('ignores an empty or non-string edition', function (mixed $edition): void {
    expect(CentreonCeipLicenseType::resolve(['epp' => ceipLicense(['edition' => $edition])]))->toBe([
        'licenseType' => 'IT Edition',
        'withFingerprint' => false,
        'ignoredEdition' => null,
    ]);
})->with([
    'empty string' => [''],
    'blank string' => ['   '],
    'null' => [null],
    'integer' => [42],
]);

it('throws on an invalid license date', function (): void {
    CentreonCeipLicenseType::resolve(['epp' => ceipLicense(['end' => 'not-a-date'])]);
})->throws(Exception::class, 'Invalid date format');
