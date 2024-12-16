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

use Symfony\Component\HttpFoundation\Response;

class DownloadPresenter implements PresenterFormatterInterface
{
    private const CSV_FILE_EXTENSION = 'csv';
    private const JSON_FILE_EXTENSION = 'json';

    public function __construct(readonly private PresenterFormatterInterface $formatter)
    {
    }

    /**
     * @inheritDoc
     */
    public function format(mixed $data, array $headers): Response
    {
        $filename = $this->generateDownloadFileName($data->filename ?? 'export');
        $headers['Content-Type'] = 'application/force-download';
        $headers['Content-Disposition'] = 'attachment; filename="' . $filename . '"';

        return $this->formatter->format($data->performanceMetrics, $headers);
    }

    /**
     * Generates download file extension depending on presenter.
     *
     * @return string
     */
    private function generateDownloadFileExtension(): string
    {
        return match ($this->formatter::class) {
            CsvFormatter::class => self::CSV_FILE_EXTENSION,
            JsonFormatter::class => self::JSON_FILE_EXTENSION,
            default => '',
        };
    }

    /**
     * Generates download file name (name + extension depending on used presenter).
     *
     * @param string $filename
     *
     * @return string
     */
    private function generateDownloadFileName(string $filename): string
    {
        $fileExtension = $this->generateDownloadFileExtension();

        return $fileExtension === '' ? $filename : $filename . '.' . $fileExtension;
    }
}
