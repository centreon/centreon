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

namespace Centreon\Application\Webservice;

use Centreon\Infrastructure\Webservice;
use Centreon\ServiceProvider;
use Pimple\Container;
use Pimple\Psr11\ServiceLocator;

/**
 * Webservice that allow to retrieve all translations in one json file.
 * If the file doesn't exist it will be created at the first reading.
 */
class CentreonI18n extends Webservice\WebServiceAbstract implements
    Webservice\WebserviceAutorizePublicInterface
{
    private ?ServiceLocator $services = null;

    /**
     * Name of web service object
     *
     * @return string
     */
    public static function getName(): string
    {
        return 'centreon_i18n';
    }

    /**
     * Return a table containing all the translations for a language.
     *
     * @throws \Exception
     * @return array
     */
    public function getTranslation(): array
    {
        try {
            $translation = $this->services
                ->get(ServiceProvider::CENTREON_I18N_SERVICE)
                ->getTranslation();
        } catch (\Exception) {
            throw new \Exception('Translation files does not exists');
        }

        return $translation;
    }

    /**
     * Return a table containing all the translations from all languages.
     *
     * @throws \Exception
     * @return array
     */
    public function getAllTranslations(): array
    {
        try {
            $translation = $this->services
                ->get(ServiceProvider::CENTREON_I18N_SERVICE)
                ->getAllTranslations();
        } catch (\Exception) {
            throw new \Exception('Translation files does not exists');
        }

        return $translation;
    }

    /**
     * Extract services that are in use only
     *
     * @param Container $di
     */
    public function setDi(Container $di): void
    {
        $this->services = new ServiceLocator($di, [
            ServiceProvider::CENTREON_I18N_SERVICE,
        ]);
    }
}
