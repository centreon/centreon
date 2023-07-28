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

namespace Centreon\Test\Api\Context;

use Behat\Gherkin\Node\PyStringNode;
use Centreon\Test\Behat\Api\Context\ApiContext;

class ClapiContext extends ApiContext
{
    private const CLAPI_BIN = '/usr/share/centreon/bin/centreon -u admin -p Centreon!2021';

    private int $lastImportCode = 0;

    private string $lastImportOutput = '';

    private int $lastExportCode = 0;

    private string $lastExportOutput = '';

    /**
     * @Given the CLAPI commands should NOT have an output with these data:
     *
     * @param PyStringNode $data
     */
    public function theClapiCommandsShouldNotHaveAnOutput(PyStringNode $data): void
    {
        if (! $this->executeImport($data->getRaw())) {
            throw new \Exception(
                sprintf(
                    "The commands got an exit code '%d' and output:\n<<<\n%s\n>>>",
                    $this->lastImportCode,
                    $this->lastImportOutput
                )
            );
        }
    }

    /**
     * @Given the CLAPI commands should have an output with these data:
     *
     * @param PyStringNode $data
     */
    public function theClapiCommandsShouldHaveAnOutput(PyStringNode $data): void
    {
        if ($this->executeImport($data->getRaw())) {
            throw new \Exception('The commands output is empty.');
        }
    }

    /**
     * @Given the CLAPI commands output should contain :string
     *
     * @param string $string
     */
    public function theClapiCommandsOutputShouldContain(string $string): void
    {
        if (! str_contains($this->lastImportOutput, $string)) {
            throw new \Exception(
                sprintf(
                    "The output didn't contain the expected string\n<<<\n%s\n>>>",
                    $this->lastImportOutput
                )
            );
        }
    }

    /**
     * @Given the CLAPI commands output should be
     *
     * @param PyStringNode $data
     */
    public function theClapiCommandsOutputShouldBe(PyStringNode $data): void
    {
        if (trim($this->lastImportOutput) !== trim($data->getRaw())) {
            throw new \Exception(
                sprintf(
                    "The output didn't match the expected\n<<<\n%s\n>>>",
                    $this->lastImportOutput
                )
            );
        }
    }

    /**
     * @Given the CLAPI export of :object filtered on :regex should be
     *
     * @param string $object
     * @param string $regex
     * @param PyStringNode $data
     */
    public function theClapiExportOfFilteredOnShouldBe(string $object, string $regex, PyStringNode $data): void
    {
        $this->executeExport($object, $regex);

        if (trim($this->lastExportOutput) !== trim($data->getRaw())) {
            throw new \Exception(
                sprintf(
                    "The export didn't match the expected\n<<<\n%s\n>>>",
                    $this->lastExportOutput
                )
            );
        }
    }

    private function executeExport(string $object, string $regexFilter): bool
    {
        $exportOptions = ' -e';
        if ('' !== $object) {
            $exportOptions .= ' --select=' . escapeshellarg($object);
        }

        $result = $this->getContainer()->execute(self::CLAPI_BIN . $exportOptions, $this->webService);

        $this->lastExportCode = (int) $result['exit_code'];
        $this->lastExportOutput = '' === $regexFilter
            ? trim($result['output'])
            : implode(
                "\n",
                array_filter(
                    explode("\n", trim($result['output'])),
                    static fn(string $line): bool => (bool) preg_match('/' . $regexFilter . '/', $line)
                )
            );

        return 0 === $this->lastExportCode && '' === $this->lastExportOutput;
    }

    private function executeImport(string $lines): bool
    {
        try {
            $tmpHandle = tmpfile();
            $tmpFilename = stream_get_meta_data($tmpHandle)['uri'];
            file_put_contents($tmpFilename, $lines);

            $fileInContainer = '/tmp/import_clapi.txt';
            $importOptions = ' -i ' . escapeshellarg($fileInContainer);
            $this->getContainer()->copyToContainer($tmpFilename, $fileInContainer, $this->webService);
            $result = $this->getContainer()->execute(self::CLAPI_BIN . $importOptions, $this->webService);

            $this->lastImportCode = (int) $result['exit_code'];
            $this->lastImportOutput = trim($result['output']);

            return 0 === $this->lastImportCode && '' === $this->lastImportOutput;
        } finally {
            fclose($tmpHandle);
        }
    }
}
