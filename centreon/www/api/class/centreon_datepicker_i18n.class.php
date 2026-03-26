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

/**
 * Class
 *
 * @class CentreonDatepickerI18n
 * @description Webservice that allow to load the datepicker library from user's language if the file exists.
 */
class CentreonDatepickerI18n extends CentreonWebService
{
    /**
     * Used to search and load the right datepicker library if it exists.
     *
     * @return string|null
     */
    public function getDatepickerLibrary()
    {
        $data = $this->arguments['data'];
        $path = __DIR__ . '/../../include/common/javascript/datepicker/';

        // check if the fullLocale library exist
        $datepickerFileToFound = 'datepicker-' . substr($data, 0, 5) . '.js';
        $valid =  file_exists($path . $datepickerFileToFound);

        // if not found, then check if the shortLocale library exist
        if ($valid !== true) {
            $datepickerFileToFound = 'datepicker-' . substr($data, 0, 2) . '.js';
            $valid = file_exists($path . $datepickerFileToFound);
        }

        // sending result
        return ($valid === true) ? $datepickerFileToFound : null;
    }
}
