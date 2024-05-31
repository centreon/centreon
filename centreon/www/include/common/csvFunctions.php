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
 * Transform plain CSV data in an associative array (first line acts as headers)
 *
 * @param array $records
 */
function csvToAssociativeArray(&$records)
{
    $headers = array_shift($records);
    foreach ($records as &$record) {
        $record = array_combine($headers, $record);
    }
}

/**
 * Return array of parsed CSV
 *
 * @param string $text
 * @param string $delim (default ';')
 * @param bool $ignoreEmptyLines (default true)
 *
 * @return bool|array False if there was a problem, otherwise the records
 */
function parseCsv(&$text, $delim = ';', $ignoreEmptyLines = true)
{
    $records = [];
    $record = [];

    $posFieldStart = 0;
    $posCur = 0;
    $recNr = 0;
    $prevCharIsDq = false;
    $insideDq = 0; // 0: to determine, 1: no, 2: yes

    $CR = "\r";
    $LF = "\n";

    $endersField = [
        $delim => $delim,
        $CR => $CR,
        $LF => $LF,
        '' => '',
    ];
    $endersRecord = [
        $CR => $CR,
        $LF => $LF,
        '' => '',
    ];

    $lastCharEndsRecord = array_key_exists($text[-1], $endersRecord);
    $textLen = strlen($text) + ($lastCharEndsRecord ? 0 : 1);
    while ($posCur < $textLen) {
        $c = $text[$posCur] ?? '';

        switch ($insideDq) {
            case 0:
                if ($c == '"') {
                    $insideDq = 2;
                    $posFieldStart = $posCur + 1;
                    break;
                } else {
                    $insideDq = 1;
                }

            case 1:
                if (array_key_exists($c, $endersField)) {
                    $insideDq = 0;
                    $isEndRec = array_key_exists($c, $endersRecord);
                    if (! ($isEndRec && $posFieldStart == $posCur && $ignoreEmptyLines && !$record)) {
                        $record[] = substr($text, $posFieldStart, $posCur - $posFieldStart);
                    }

                    $posFieldStart = $posCur + 1;

                    if ($isEndRec) {
                        if ($c == $CR) {
                            $posFieldStart++;
                            $posCur++;
                        }

                        if ($record) {
                            $records[$recNr] = $record;
                            $record = [];
                            $recNr++;
                        }
                    }
                }
                break;

            case 2:
                if ($prevCharIsDq) {
                    if ($c == '"') {
                        $prevCharIsDq = false;
                    } elseif (array_key_exists($c, $endersField)) {
                        $prevCharIsDq = false;
                        $isEndRec = array_key_exists($c, $endersRecord);
                        if (! ($isEndRec && $posFieldStart == $posCur && $ignoreEmptyLines && !$record)) {
                            $record[] = str_replace('""', '"', substr($text, $posFieldStart, $posCur - $posFieldStart - 1));
                        }

                        $posFieldStart = $posCur + 1;

                        if ($isEndRec) {
                            if ($c == $CR) {
                                $posFieldStart++;
                                $posCur++;
                            }

                            if ($record) {
                                $records[$recNr] = $record;
                                $record = [];
                                $recNr++;
                            }
                        }
                        $insideDq = 0;
                    } else {
                        return false; // malformed csv
                    }
                    break;
                }
                if ($c == '"') {
                    $prevCharIsDq = true;
                } elseif ($c == '') {
                    return false; // malformed csv
                }
                break;
        }

        $posCur++;
    }

    // truncated input
    if ($insideDq == 2) {
        return false;
    }

    return $records;
}
