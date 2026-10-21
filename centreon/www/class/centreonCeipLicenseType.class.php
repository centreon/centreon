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

/**
 * Resolve the license type sent to Pendo (CEIP) from the valid licenses of the platform.
 *
 * @class CentreonCeipLicenseType
 */
final class CentreonCeipLicenseType
{
    /**
     * CIM editions that have their own license type label, historically sent to Pendo.
     * Any other combination (unknown edition, module licenses bought on top of an edition
     * such as IT + BAM, standalone BAM, MAP or MBI subscriptions...) is reported as CUSTOM.
     */
    private const EDITION_LABELS = [
        'IT-100' => 'IT-100 Edition',
        'IT' => 'IT Edition',
        'BE' => 'Business Edition',
        'MSP' => 'MSP Edition',
        'PRO' => 'PRO Edition',
        'CUSTOM' => 'Custom Edition',
    ];
    private const MSP_EDITION_LABEL = 'MSP Edition';

    /** CIM editions that do not include the BAM, MAP and MBI modules. */
    private const EDITIONS_WITHOUT_MODULES = ['IT-100', 'IT', 'PRO'];

    /** CIM editions for which the platform fingerprint is sent. */
    private const FINGERPRINTED_EDITIONS = ['BE', 'MSP', 'CUSTOM'];

    /** Modules sold on top of (or without) EPP. */
    private const ADDITIONAL_MODULES = ['bam', 'map', 'mbi'];

    /**
     * The license type is computed from the module, the host limit and the license duration.
     * When licenses carry the CIM edition explicitly (licensing.edition), it takes precedence over
     * the computed type, and the fingerprint follows it: the edition is kept only when all the
     * declared editions are the same standard one, otherwise the platform is reported as CUSTOM.
     * An edition that does not include the BAM, MAP and MBI modules is also reported as CUSTOM
     * as soon as one of these module licenses is present, whether it declares an edition or not.
     * The CRM asset ID (licensing.asset_id) is returned for MSP platforms only, to bill them.
     *
     * @param array<string, array<array-key, mixed>> $licenses valid licenses indexed by module, in lookup order
     *
     * @throws Exception when a license start or end date is invalid
     *
     * @return array{licenseType: string, withFingerprint: bool, assetId: string|null, unknownEdition: string|null}
     */
    public static function resolve(array $licenses): array
    {
        $licenseType = 'Open Source';
        $withFingerprint = false;
        $editions = [];
        $assetId = null;
        $hasModuleLicense = false;

        foreach ($licenses as $module => $license) {
            $edition = self::readString($license, 'edition');
            if ($edition !== null) {
                $editions[strtoupper($edition)] = $edition;
            }
            $assetId ??= self::readString($license, 'asset_id');
            $durationInDays = self::durationInDays($license);
            $isLongUnlimitedLicense = (int) $license['licensing']['hosts'] === -1 && $durationInDays > 90;

            if ($module === 'epp') {
                $licenseType = 'IT Edition';
                if (($license['licensing']['type'] ?? null) === 'IT100') {
                    $licenseType = 'IT-100 Edition';
                } elseif ($isLongUnlimitedLicense) {
                    $licenseType = self::MSP_EDITION_LABEL;
                    $withFingerprint = true;
                }
            } elseif (in_array($module, self::ADDITIONAL_MODULES, true)) {
                $hasModuleLicense = true;
                $licenseType = $isLongUnlimitedLicense ? self::MSP_EDITION_LABEL : 'Business Edition';
                $withFingerprint = true;
            }
        }

        $unknownEdition = null;
        if ($editions !== []) {
            $editionKey = count($editions) === 1 ? array_key_first($editions) : 'CUSTOM';
            if (! isset(self::EDITION_LABELS[$editionKey])) {
                $unknownEdition = $editions[$editionKey];
                $editionKey = 'CUSTOM';
            } elseif ($hasModuleLicense && in_array($editionKey, self::EDITIONS_WITHOUT_MODULES, true)) {
                $editionKey = 'CUSTOM';
            }
            $licenseType = self::EDITION_LABELS[$editionKey];
            $withFingerprint = in_array($editionKey, self::FINGERPRINTED_EDITIONS, true);
        }

        return [
            'licenseType' => $licenseType,
            'withFingerprint' => $withFingerprint,
            'assetId' => $licenseType === self::MSP_EDITION_LABEL ? $assetId : null,
            'unknownEdition' => $unknownEdition,
        ];
    }

    /**
     * Read a non-empty string property of the licensing section, trimmed.
     *
     * @param array<array-key, mixed> $license
     */
    private static function readString(array $license, string $property): ?string
    {
        $value = $license['licensing'][$property] ?? null;
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    /**
     * @param array<array-key, mixed> $license
     *
     * @throws Exception
     */
    private static function durationInDays(array $license): int
    {
        $start = self::parseDate((string) $license['licensing']['start']);
        $end = self::parseDate((string) $license['licensing']['end']);

        return (int) ($end->diff($start)->days ?? 0);
    }

    /**
     * Parse a 'Y-m-d' date, rejecting invalid calendar dates (createFromFormat rolls
     * '2025-02-30' over to March and only warns, so a false check alone is not enough).
     *
     * @throws Exception when the date is not a valid 'Y-m-d' date
     */
    private static function parseDate(string $date): DateTime
    {
        $parsed = DateTime::createFromFormat('Y-m-d', $date);
        $errors = DateTime::getLastErrors();
        if (
            $parsed === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        ) {
            throw new Exception('Invalid date format');
        }

        return $parsed;
    }
}
