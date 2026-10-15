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

/**
 * Resolve the license type sent to Pendo (CEIP) from the valid licenses of the platform.
 *
 * @class CentreonCeipLicenseType
 */
final class CentreonCeipLicenseType
{
    /**
     * CIM editions allowed in the licensing.edition property of the license file,
     * with the license type label historically sent to Pendo.
     * CUSTOM is used for standalone module subscriptions (BAM, MAP, MBI without EPP).
     */
    private const EDITION_LABELS = [
        'IT-100' => 'IT-100 Edition',
        'IT' => 'IT Edition',
        'BE' => 'Business Edition',
        'MSP' => 'MSP Edition',
        'PRO' => 'PRO Edition',
        'CUSTOM' => 'Custom Edition',
    ];

    /** CIM editions for which the platform fingerprint is sent. */
    private const FINGERPRINTED_EDITIONS = ['BE', 'MSP', 'CUSTOM'];

    /** Modules sold on top of (or without) EPP. */
    private const ADDITIONAL_MODULES = ['bam', 'map', 'mbi'];

    /**
     * The license type is computed from the module, the host limit and the license duration.
     * When a license carries the CIM edition explicitly (licensing.edition), the first one found
     * takes precedence over the computed type, and the fingerprint follows the declared edition.
     *
     * @param array<string, array<array-key, mixed>> $licenses valid licenses indexed by module, in lookup order
     *
     * @throws Exception when a license start or end date is invalid
     *
     * @return array{licenseType: string, withFingerprint: bool, ignoredEdition: string|null}
     */
    public static function resolve(array $licenses): array
    {
        $licenseType = 'Open Source';
        $withFingerprint = false;
        $edition = null;

        foreach ($licenses as $module => $license) {
            $edition ??= self::readEdition($license);
            $durationInDays = self::durationInDays($license);
            $isLongUnlimitedLicense = (int) $license['licensing']['hosts'] === -1 && $durationInDays > 90;

            if ($module === 'epp') {
                $licenseType = 'IT Edition';
                if (($license['licensing']['type'] ?? null) === 'IT100') {
                    $licenseType = 'IT-100 Edition';
                } elseif ($isLongUnlimitedLicense) {
                    $licenseType = 'MSP Edition';
                    $withFingerprint = true;
                }
            } elseif (in_array($module, self::ADDITIONAL_MODULES, true)) {
                $licenseType = $isLongUnlimitedLicense ? 'MSP Edition' : 'Business Edition';
                $withFingerprint = true;
            }
        }

        if ($edition !== null && isset(self::EDITION_LABELS[$edition])) {
            return [
                'licenseType' => self::EDITION_LABELS[$edition],
                'withFingerprint' => in_array($edition, self::FINGERPRINTED_EDITIONS, true),
                'ignoredEdition' => null,
            ];
        }

        return [
            'licenseType' => $licenseType,
            'withFingerprint' => $withFingerprint,
            'ignoredEdition' => $edition,
        ];
    }

    /**
     * @param array<array-key, mixed> $license
     */
    private static function readEdition(array $license): ?string
    {
        $edition = $license['licensing']['edition'] ?? null;
        if (! is_string($edition) || trim($edition) === '') {
            return null;
        }

        return strtoupper(trim($edition));
    }

    /**
     * @param array<array-key, mixed> $license
     *
     * @throws Exception
     */
    private static function durationInDays(array $license): int
    {
        $start = DateTime::createFromFormat('Y-m-d', (string) $license['licensing']['start'])
            ?: throw new Exception('Invalid date format');
        $end = DateTime::createFromFormat('Y-m-d', (string) $license['licensing']['end'])
            ?: throw new Exception('Invalid date format');

        return (int) ($end->diff($start)->days ?? 0);
    }
}
