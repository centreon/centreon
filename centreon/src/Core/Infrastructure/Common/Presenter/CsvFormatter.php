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

namespace Core\Infrastructure\Common\Presenter;

use Core\Infrastructure\Common\Exception\CsvFormatterException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Class
 *
 * @class CsvFormatter
 * @package Core\Infrastructure\Common\Presenter
 */
class CsvFormatter implements PresenterFormatterInterface
{
    /**
     * @param mixed $data
     * @param array<string, mixed> $headers
     *
     * @return Response
     */
    public function format(mixed $data, array $headers): Response
    {
        return new StreamedResponse(callback: function() use ($data, $headers): void {
            if (! is_array($data) && ! $data instanceof \Traversable) {
                throw new CsvFormatterException(
                    'Data to export in csv must be iterable,' . gettype($data) . ' given',
                    ['csv_data' => $data, 'http_headers' => $headers]
                );
            }

            $lineHeadersCreated = false;

            $handle = fopen('php://output', 'r+');

            if ($handle === false) {
                throw new CsvFormatterException(
                    'Unable to open the output buffer to export csv',
                    ['http_headers' => $headers]
                );
            }

            foreach ($data as $dataItem) {
                if (! is_array($dataItem) && ! $data instanceof \Traversable) {
                    throw new CsvFormatterException(
                        'Data to export in csv must be an array, ' . gettype($dataItem) . ' given',
                        ['csv_data' => $dataItem, 'http_headers' => $headers]
                    );
                }
                if (! $lineHeadersCreated) {
                    $columnNames = array_keys($dataItem);
                    if (fputcsv($handle, $columnNames, ';') === false) {
                        throw new CsvFormatterException(
                            'Unable to write the headers in the csv file',
                            ['csv_header' => $columnNames, 'http_headers' => $headers]
                        );
                    }
                    $lineHeadersCreated = true;
                }

                $columnValues = array_values($dataItem);

                if (fputcsv($handle, $columnValues, ';') === false) {
                    throw new CsvFormatterException(
                        'Unable to write the data in the csv file',
                        ['csv_data' => $dataItem, 'csv_header' => $headers, 'http_headers' => $headers]
                    );
                }
            }

            if (fclose($handle) === false) {
                throw new CsvFormatterException(
                    'Unable to close the output buffer to export csv',
                    ['http_headers' => $headers]
                );
            }
        }, headers: $headers);
    }
}

