<?php
/*
 * Copyright 2005-2019 Centreon
 * Centreon is developed by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */
namespace Centreon\Domain\Service;

use CentreonLegacy\Core\Module\Information;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class to manage translation of centreon and its extensions
 */
class I18nService
{
    /**
     * @var Information
     */
    private $modulesInformation;

    /**
     * @var String
     */
    private $lang;

    /**
     * @var Finder
     */
    private $finder;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * I18nService constructor
     *
     * @param Information $modulesInformation To get information from centreon modules
     */
    public function __construct(Information $modulesInformation, Finder $finder, Filesystem $filesystem)
    {
        $this->modulesInformation = $modulesInformation;
        $this->initLang();
        $this->finder = $finder;
        $this->filesystem = $filesystem;
    }

    /**
     * Retrieves all available locales in format xy_XY
     * en_US will always be present in the first place
     * @return array
     */
    public static function getAvailableCentreonLanguages(): array
    {
        $dirs = (new Finder())
            ->directories()
            ->in(__DIR__ . "/../../../../www/locale")
            ->depth('== 0')
            ->sortByName();
        $locales = [];
        foreach ($dirs as $dir) {
            if (! preg_match('/^([a-z]{2}_[A-Z]{2})/', $dir->getBasename(), $matches)) {
                continue;
            }
            $locales[] = $matches[1];
        }
        $enUSIndex = array_search("en_US", $locales, true);
        if ($enUSIndex !== false) {
            array_splice($locales, $enUSIndex, 1);
        }
        $locales = array_merge(["en_US"], $locales);
        return array_values(array_unique($locales));
    }

    /**
     * Guesses locale from a provided request or from current HEADERS (create request from globals)
     * @param Request|null $request
     * @return string
     */
    public static function guessLocaleFromRequest(?Request $request = null): string
    {
        if (!isset($request)) {
            $request = Request::createFromGlobals();
        }

        $localeMap = [];
        $locales = self::getAvailableCentreonLanguages();
        foreach ($locales as $locale) {
            [$short, $country] = explode("_", $locale);
            // when short notation already set only overwrite when short = country
            if (!isset($localeMap[$short]) || strtolower($short) === strtolower($country)) {
                $localeMap[$short] = $locale;
            }
            $localeMap[$locale] = $locale;
        }

        // getPreferredLanguage will always return a value
        // if no preferred value can be extracted from the request headers, the first provided locale is returned
        // e.g., the request accepts es-CO - it will be matched with es
        // the more specific case pt-BR is also matched, in any other pt case the standard pt (pt_PT) is matched
        // getPreferredLanguage normalizes the provided locales automatically (_ to -, casing ecc.)
        $preferredLanguage = $request->getPreferredLanguage(array_keys($localeMap));

        return $localeMap[$preferredLanguage] ?? $localeMap[array_key_first($localeMap)];
    }

    /**
     * Initialize lang object to bind language
     *
     * @return void
     */
    private function initLang(): void
    {
        $this->lang = getenv('LANG');

        if (strstr($this->lang, '.UTF-8') === false) {
            $this->lang .= '.UTF-8';
        }
    }

    /**
     * Get translation from centreon and its extensions
     *
     * @return array
     */
    public function getTranslation(): array
    {
        $centreonTranslation = $this->getCentreonTranslation();
        $extensionsTranslation = $this->getExtensionsTranslation();

        return array_replace_recursive($centreonTranslation, $extensionsTranslation);
    }

    /**
     * Get all translations fron Centreon and its modules
     *
     * @return array
     */
    public function getAllTranslations(): array
    {
        $centreonTranslation = $this->getAllCentreonTranslation();
        $modulesTranslation = $this->getAllModulesTranslation();

        return array_replace_recursive($centreonTranslation, $modulesTranslation);
    }

    /**
     * Get all translations from centreon
     *
     * @return array
     */
    private function getCentreonTranslation(): array
    {
        $data = [];

        $translationPath = __DIR__ . "/../../../../www/locale/{$this->lang}/LC_MESSAGES";
        $translationFile = "messages.ser";

        if ($this->filesystem->exists($translationPath . "/" . $translationFile)) {
            $files = $this->finder
                ->name($translationFile)
                ->in($translationPath);

            foreach ($files as $file) {
                $data = unserialize($file->getContents());
            }
        }

        return $data;
    }

    /**
     * Get translation from centreon
     *
     * @return array
     */
    private function getAllCentreonTranslation(): array
    {
        $data = [];

        $languages = array_map(static function ($i) {
            return $i . ".UTF-8";
        }, self::getAvailableCentreonLanguages());

        foreach ($languages as $language) {
            $translationPath = __DIR__ . "/../../../../www/locale/{$language}/LC_MESSAGES";
            $translationFile = "messages.ser";

            if ($this->filesystem->exists($translationPath . "/" . $translationFile)) {
                $files = $this->finder
                    ->name($translationFile)
                    ->in($translationPath);

                foreach ($files as $file) {
                    $data += unserialize($file->getContents());
                }
            }
        }

        return $data;
    }

    /**
     * Get translation from each installed module
     *
     * @return array
     */
    private function getExtensionsTranslation(): array
    {
        $data = [];

        // loop over each installed modules to get translation
        foreach (array_keys($this->modulesInformation->getInstalledList()) as $module) {
            $translationPath = __DIR__ . "/../../../../www/modules/{$module}/locale/{$this->lang}/LC_MESSAGES";
            $translationFile = "messages.ser";

            if ($this->filesystem->exists($translationPath . "/" . $translationFile)) {
                $files = $this->finder
                    ->name($translationFile)
                    ->in($translationPath);

                foreach ($files as $file) {
                    $data = array_replace_recursive(
                        $data,
                        unserialize($file->getContents())
                    );
                }
            }
        }

        return $data;
    }

    /**
     * Get all translation from each installed module
     *
     * @return array
     */
    private function getAllModulesTranslation(): array
    {
        $data = [];

        $languages = array_map(static function ($i) {
            return $i . ".UTF-8";
        }, self::getAvailableCentreonLanguages());

        foreach ($languages as $language) {
            // loop over each installed modules to get translation
            foreach (array_keys($this->modulesInformation->getInstalledList()) as $module) {
                $translationPath = __DIR__ . "/../../../../www/modules/{$module}/locale/{$language}/LC_MESSAGES";
                $translationFile = "messages.ser";

                if ($this->filesystem->exists($translationPath . "/" . $translationFile)) {
                    $files = $this->finder
                        ->name($translationFile)
                        ->in($translationPath);

                    foreach ($files as $file) {
                        $data = array_replace_recursive(
                            $data,
                            unserialize($file->getContents())
                        );
                    }
                }
            }
        }

        return $data;
    }
}
