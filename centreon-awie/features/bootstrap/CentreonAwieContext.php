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

use Centreon\Test\Behat\CentreonContext;

class CentreonAwieContext extends CentreonContext
{
    /**
     * @Given I am logged in a Centreon server with Awie installed
     */
    public function iAmLoggedInACentreonServerWithAWIEInstalled(): void
    {
        $this->launchCentreonWebContainer('awie');
        $this->iAmLoggedIn();
    }

    public function iAmOnTheExportPage(): void
    {
        $this->visit('main.php?p=61201');
        // Check that page is valid for this class.
        $mythis = $this;
        $this->spin(
            function ($context) {
                return $context->getSession()->getPage()->has('css', '#poller');
            }
        );
    }

    public function iAmOnTheImportPage(): void
    {
        $this->visit('main.php?p=61202');
        $mythis = $this;
        $this->spin(
            function ($context) {
                return $context->getSession()->getPage()->has('css', '#file');
            }
        );
    }
}
