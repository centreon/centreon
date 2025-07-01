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

namespace Tools\PhpStan;

use PHPStan\Analyser\Error;
use PHPStan\Command\AnalysisResult;
use PHPStan\Command\ErrorFormatter\ErrorFormatter;
use PHPStan\Command\Output;

/**
 * This class is used to set a custom formatter to phpstan exporting absolute paths.
 */
class AbsolutePathErrorFormatter implements ErrorFormatter
{
    /**
     * Format errors output.
     *
     * @param AnalysisResult $analysisResult Result of check style
     * @param Output $output Output stream to write
     *
     * @return int If there are some errors
     */
    public function formatErrors(
        AnalysisResult $analysisResult,
        Output $output
    ): int {
        $output->writeRaw('<?xml version="1.0" encoding="UTF-8"?>');
        $output->writeLineFormatted('');
        $output->writeRaw('<checkstyle>');
        $output->writeLineFormatted('');

        foreach ($this->groupByFile($analysisResult) as $filePath => $errors) {
            $filePath = $this->parseFilePath($filePath);
            $output->writeRaw(
                sprintf(
                    '<file name="%s">',
                    $filePath
                )
            );
            $output->writeLineFormatted('');

            foreach ($errors as $error) {
                $output->writeRaw(
                    sprintf(
                        '  <error line="%d" column="1" severity="error" message="%s" source="PHPStan" />',
                        $this->escape((string) $error->getLine()),
                        $this->escape((string) $error->getMessage())
                    )
                );
                $output->writeLineFormatted('');
            }
            $output->writeRaw('</file>');
            $output->writeLineFormatted('');
        }

        $notFileSpecificErrors = $analysisResult->getNotFileSpecificErrors();

        if ($notFileSpecificErrors !== []) {
            $output->writeRaw('<file>');
            $output->writeLineFormatted('');

            foreach ($notFileSpecificErrors as $error) {
                $output->writeRaw(
                    sprintf('  <error severity="error" message="%s" source="PHPStan" />', $this->escape($error))
                );
                $output->writeLineFormatted('');
            }

            $output->writeRaw('</file>');
            $output->writeLineFormatted('');
        }

        if ($analysisResult->hasWarnings()) {
            $output->writeRaw('<file>');
            $output->writeLineFormatted('');

            foreach ($analysisResult->getWarnings() as $warning) {
                $output->writeRaw(
                    sprintf('  <error severity="warning" message="%s" source="PHPStan" />', $this->escape($warning))
                );
                $output->writeLineFormatted('');
            }

            $output->writeRaw('</file>');
            $output->writeLineFormatted('');
        }

        $output->writeRaw('</checkstyle>');
        $output->writeLineFormatted('');

        return $analysisResult->hasErrors() ? 1 : 0;
    }

    /**
     * Escapes values for using in XML.
     *
     * @param string $string
     *
     * @return string
     */
    protected function escape(string $string): string
    {
        return htmlspecialchars($string, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    /**
     * Group errors by file.
     *
     * @param AnalysisResult $analysisResult
     *
     * @return array<string, array<Error>> array that have as key the absolute path of file
     *                                     and as value an array with occurred errors
     */
    private function groupByFile(AnalysisResult $analysisResult): array
    {
        $files = [];

        /** @var Error $fileSpecificError */
        foreach ($analysisResult->getFileSpecificErrors() as $fileSpecificError) {
            $files[$fileSpecificError->getFile()][] = $fileSpecificError;
        }

        return $files;
    }

    /**
     * Remove useless information like trait context.
     *
     * @param string $filePath Absolute file path
     *
     * @return string File path with removed useless information
     */
    private function parseFilePath(string $filePath): string
    {
        if (preg_match('/(.+)\s+\(in context/', $filePath, $matches)) {
            $filePath = $matches[1];
        }

        return $this->escape($filePath);
    }
}
