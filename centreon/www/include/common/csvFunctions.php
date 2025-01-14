<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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
 *
 * @param string $text
 * @param string $delimiter (default ';')
 *
 * @return Generator
 */
function readCsvLine(string &$text, string $delimiter = ';'): Generator
{
    $handle = @fopen('php://memory', 'r+');
    if ($handle === false) {
        throw new RuntimeException('Failed to create memory stream');
    }
    if (fwrite($handle, $text) === false) {
        fclose($handle);
        throw new RuntimeException('Failed to write to memory stream');
    }
    rewind($handle);
    while ($data = fgetcsv($handle, null, $delimiter, '"', '')) {
        yield $data;
    }
    fclose($handle);
}

/**
 * @param $text
 * @param bool $useCsvHeaderAsKey If true, the first line will be used as headers
 * @param string $delimiter The delimiter used in the CSV file
 *
 * @return array
 */
function csvToArray(&$text, bool $useCsvHeaderAsKey, string $delimiter = ';'): array
{
    $lineNumber = 0;
    $delimiterNumber = 0;
    $data = [];
    $headers = [];
    foreach (readCsvLine($text, $delimiter) as $record) {
        if ($lineNumber++ === 0) {
            $headers = $record;
            $delimiterNumber = count($headers);
            if (! $useCsvHeaderAsKey) {
                $data[] = $headers;
            }
            continue;
        }
        $record = explode($delimiter, implode($delimiter, $record), $delimiterNumber);
        if ($useCsvHeaderAsKey) {
            if (count($record) !== count($headers)) {
                throw new RuntimeException(
                    sprintf(
                        'CSV record on line %d has %d fields, expected %d',
                        $lineNumber,
                        count($record),
                        count($headers)
                    )
                );
            }
            $data[] = array_combine($headers, $record);
        } else {
            $data[] = $record;
        }
    }
    return $data;
}
