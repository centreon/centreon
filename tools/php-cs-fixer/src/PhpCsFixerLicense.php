<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Tools\PhpCsFixer;

class PhpCsFixerLicense
{
    private const LICENSE_APACHE = 'apache';
    private const LICENSE_PRIVATE = 'private';
    private const LICENSES = [
        'centreon/centreon-test-lib' => self::LICENSE_APACHE,
        'centreon/centreon' => self::LICENSE_APACHE,
        'centreon/centreon-anomaly-detection' => self::LICENSE_PRIVATE,
        'centreon/centreon-autodiscovery' => self::LICENSE_PRIVATE,
        'centreon/centreon-bam' => self::LICENSE_PRIVATE,
        'centreon/centreon-cloud-business-extensions' => self::LICENSE_PRIVATE,
        'centreon/centreon-cloud-extensions' => self::LICENSE_PRIVATE,
        'centreon/centreon-it-edition-extensions' => self::LICENSE_PRIVATE,
        'centreon/centreon-license-manager' => self::LICENSE_PRIVATE,
        'centreon/centreon-map' => self::LICENSE_PRIVATE,
        'centreon/centreon-mbi' => self::LICENSE_PRIVATE,
        'centreon/centreon-pp-manager' => self::LICENSE_PRIVATE,
    ];

    private const LICENCE_TEMPLATES_DIR = __DIR__ . '/../templates/';

    /**
     * Get the license header as a php comment.
     *
     * @param string $projectLicense
     *
     * @return string
     */
    public static function getLicenseHeaderAsPhpComment(string $projectLicense): string
    {
        if ('' === ($header = self::getLicenseHeaderAsText($projectLicense))) {
            return '';
        }

        $lines = explode("\n", $header);
        $lines = array_map(static fn(string $line): string => rtrim(' * ' . $line), $lines);

        return "/*\n" . implode("\n", $lines) . "\n */\n";
    }

    /**
     * Recursively find the root main project which uses this project in its vendor.
     *
     * @param string $directory
     *
     * @return string
     */
    public static function detectCentreonProjectLicense(string $directory): string
    {
        // "end" conditions -> '', '.', '/'
        while (\mb_strlen($directory) > 1) {
            if (
                // A composer.json file is mandatory.
                is_file($composerFile = $directory . '/composer.json')

                // Avoid a composer.json from inside the Centreon vendor directory.
                && ! str_ends_with(dirname($directory), '/vendor/centreon')

                // There should have a defined license.
                && ($license = self::getCentreonProjectLicense($composerFile))
            ) {
                return $license;
            }

            $directory = dirname($directory);
        }

        return '';
    }

    /**
     * Return the header based on the project license type.
     *
     * @param string $projectLicense
     *
     * @return string
     */
    public static function getLicenseHeaderAsText(string $projectLicense): string
    {
        $content = match ($projectLicense) {
            self::LICENSE_APACHE => (string) file_get_contents(self::LICENCE_TEMPLATES_DIR. 'license.apache.tpl'),
            self::LICENSE_PRIVATE => (string) file_get_contents(self::LICENCE_TEMPLATES_DIR. 'license.private.tpl'),
            default => '',
        };

        return str_replace('{YEAR}', (string) (new \DateTime())->format('Y'), $content);
    }

    /**
     * Extract the project name from the composer json file
     * and then return the related license if defined.
     *
     * @param string $composerFile
     *
     * @return string|null
     */
    private static function getCentreonProjectLicense(string $composerFile): ?string
    {
        try {
            $composerContent = (string) file_get_contents($composerFile);
            $composerData = json_decode($composerContent, true, 512, JSON_THROW_ON_ERROR);
            $projectName = $composerData['name'] ?? null;

            if ($projectName && isset(self::LICENSES[$projectName])) {
                return self::LICENSES[$projectName];
            }
        } catch (\JsonException) {
        }

        return null;
    }
}
