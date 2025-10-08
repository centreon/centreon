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
 * @class ImportExportContext
 */
class ImportExportContext extends CentreonAwieContext
{
    /**
     * @When I export an object
     */
    public function iExportAnObject(): void
    {
        $this->iAmOnTheExportPage();
        $this->assertFind('css', '#contact')->click();
        $this->assertFind('css', '.bt_success')->click();
    }

    /**
     * @Then I have a file
     */
    public function iHaveAFile(): void
    {
        $mythis = $this;

        $this->spin(
            function ($context) {
                if ($context->getSession()->getPage()->has('css', '.loadingWrapper')) {
                    return ! $context->assertFind('css', '.loadingWrapper')->isVisible();
                }

                return true;
            }
        );

        $cmd = 'ls /tmp';
        $output = $this->container->execute(
            $cmd,
            'web'
        );
        $output = explode("\n", $output['output']);
        $fileCreate = false;
        foreach ($output as $file) {
            if (str_ends_with("{$file}", 'zip')) {
                $fileCreate = true;
            }
        }

        if (! $fileCreate) {
            throw new Exception('File not create');
        }
    }
}
