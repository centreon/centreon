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

namespace Core\Metric\Infrastructure\Graph;

use App\Kernel;
use Core\Metric\Domain\Model\MetricsDataSource;
use Psr\Container\ContainerInterface;

/**
 * Metrics data source switch for the legacy pages and classes (www/): whether the graphs are read
 * from VictoriaMetrics, the "not available" answers of the RRD-only features, and the rrdtool
 * binary check of the RRD source.
 */
final class LegacyMetricsDataSource
{
    /**
     * @param mixed $optionValue value of the option "metrics_data_source" (null when absent)
     */
    public static function isVictoriaMetricsOption(mixed $optionValue): bool
    {
        return MetricsDataSource::fromOption(is_string($optionValue) ? $optionValue : null)
            === MetricsDataSource::VictoriaMetrics;
    }

    /**
     * @param \PDO $db connection to the configuration database (CentreonDB)
     */
    public static function isVictoriaMetrics(\PDO $db): bool
    {
        $statement = $db->prepare("SELECT `value` FROM `options` WHERE `key` = 'metrics_data_source'");
        $statement->execute();

        return self::isVictoriaMetricsOption($statement->fetchColumn());
    }

    /**
     * Reader of the graph values in VictoriaMetrics, from the Symfony container.
     */
    public static function getGraphReader(): TimeSeriesGraphReader
    {
        $locator = Kernel::createForWeb()->getContainer()->get('legacy.service_locator');
        $reader = $locator instanceof ContainerInterface ? $locator->get('metrics_graph_reader') : null;
        if (! $reader instanceof TimeSeriesGraphReader) {
            throw new \LogicException('The metrics graph reader is not available to the legacy code');
        }

        return $reader;
    }

    /**
     * @throws \RuntimeException when the rrdtool binary cannot be run
     *
     * @return string the binary path, for proc_open()
     */
    public static function checkRrdtool(mixed $path): string
    {
        $binary = is_string($path) ? trim($path) : '';
        if ($binary === '' || ! is_file($binary) || ! is_executable($binary)) {
            throw new \RuntimeException(sprintf(
                _('rrdtool cannot be run (%s): install it or store the metrics in VictoriaMetrics'),
                $binary === '' ? _('no path configured') : $binary
            ));
        }

        return $binary;
    }

    public static function graphImagesNotAvailableMessage(): string
    {
        return _('Graph images are not available when the metrics are stored in VictoriaMetrics');
    }

    public static function pollerStatisticsNotAvailableMessage(): string
    {
        return _('The poller statistics graphs are not available when the metrics are stored in VictoriaMetrics');
    }

    public static function rrdtoolNotUsedMessage(): string
    {
        return _('RRDtool is not used: the metrics are stored in VictoriaMetrics');
    }

    /**
     * PNG image holding $message, sent instead of a graph image (HTTP 501 by default).
     */
    public static function renderMessageImage(string $message, int $width = 500, int $height = 120): string
    {
        $image = imagecreatetruecolor(max(1, $width), max(1, $height));
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 0xFF, 0xFF, 0xFF));
        $color = (int) imagecolorallocate($image, 0x33, 0x33, 0x33);
        $font = 3;
        $lineWidth = max(10, intdiv($width - 20, imagefontwidth($font)));
        // the built-in GD fonts are 8-bit: accents only survive in ISO-8859-1
        $latin1 = (string) mb_convert_encoding($message, 'ISO-8859-1', 'UTF-8');
        $lines = explode("\n", wordwrap($latin1, $lineWidth, "\n", true));
        $top = max(5, intdiv($height - count($lines) * (imagefontheight($font) + 4), 2));
        foreach ($lines as $line) {
            imagestring($image, $font, 10, $top, $line, $color);
            $top += imagefontheight($font) + 4;
        }
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /**
     * Sends $message as a PNG image and stops the script.
     */
    public static function displayMessageImage(string $message, int $statusCode = 501): never
    {
        $png = self::renderMessageImage($message);
        if (! headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: image/png');
            header('Content-Length: ' . mb_strlen($png, '8bit'));
        }
        echo $png;

        exit;
    }
}
