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
 * @return Generator
 */
function readCsvLine(string &$text): Generator
{
    $len = strlen($text);
    $cursor = 0;
    $startOfLine = 0;
    while ($cursor < $len) {
        $char = mb_ord($text[$cursor]);
        if ($char === 10) {
            $lineSize = ($cursor > 0 && mb_ord($text[$cursor - 1]) === 13)
                ? $cursor - $startOfLine - 1
                : $cursor - $startOfLine;
            yield mb_substr($text, $startOfLine, $lineSize);
            $startOfLine = $cursor + 1;
        }
        $cursor++;
    }
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
    foreach (readCsvLine($text) as $line) {
        if ($lineNumber++ === 0) {
            $headers = explode($delimiter, $line);
            $delimiterNumber = count($headers);
            if (! $useCsvHeaderAsKey) {
                $data[] = $headers;
            }
            continue;
        }
        $record = explode($delimiter, $line, $delimiterNumber);
        $data[] = $useCsvHeaderAsKey ? array_combine($headers, $record) : $record;
    }
    return $data;
}
