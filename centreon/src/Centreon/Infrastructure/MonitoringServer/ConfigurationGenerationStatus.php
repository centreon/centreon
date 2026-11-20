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

namespace Centreon\Infrastructure\MonitoringServer;

use Centreon\Domain\MonitoringServer\Exception\IncompleteGenerationException;

/**
 * Tracks whether the last configuration generation of each monitoring server completed.
 *
 * A marker file is written when a generation starts and removed once it has fully completed. A generation that
 * dies on the way (PHP memory exhausted, timeout, exception...) leaves it behind: moving the configuration files
 * or restarting the monitoring server is then refused, instead of deploying the files of an older generation, or
 * a mix of both (the engine files are replaced before the broker ones). No marker (never generated since this
 * check exists) keeps the previous behaviour.
 *
 * The marker sits next to the generated directories (engine/, broker/...), not inside them: their whole content
 * is sent to the monitoring servers.
 */
class ConfigurationGenerationStatus
{
    private const MARKER_SUFFIX = '.generation-incomplete';

    /**
     * @param string $generatePath directory of the generated configuration (_CENTREON_CACHEDIR_/config)
     */
    public function __construct(private readonly string $generatePath)
    {
    }

    public static function create(): self
    {
        return new self(_CENTREON_CACHEDIR_ . '/config');
    }

    /**
     * To be called before anything of the monitoring server is generated.
     *
     * Never blocks the generation: when the marker cannot be written, the generation goes on unguarded, as before
     * this check existed.
     */
    public function markStarted(int $monitoringServerId, string $username): void
    {
        $marker = $this->getMarkerPath($monitoringServerId);
        // Replaced, not rewritten: the previous one may belong to another user (CLAPI run as root).
        @unlink($marker);
        if (@file_put_contents($marker, date('c') . ' ' . $username . "\n") === false) {
            error_log(sprintf('Cannot write the configuration generation marker %s', $marker));
        }
    }

    /**
     * To be called once everything of the monitoring server has been generated.
     */
    public function markCompleted(int $monitoringServerId): void
    {
        $marker = $this->getMarkerPath($monitoringServerId);
        if (file_exists($marker) && ! @unlink($marker)) {
            // The path is for the administrator, in the logs only.
            error_log(sprintf('Cannot remove the configuration generation marker %s', $marker));

            throw new \RuntimeException(sprintf(
                _('Cannot mark the configuration generation of the monitoring server #%d as complete, see the logs'),
                $monitoringServerId
            ));
        }
    }

    /**
     * @throws IncompleteGenerationException
     */
    public function assertCompleted(int $monitoringServerId, string $monitoringServerName): void
    {
        $marker = $this->getMarkerPath($monitoringServerId);
        if (! file_exists($marker)) {
            return;
        }

        $startedBy = trim((string) @file_get_contents($marker));
        // The path is for the administrator, in the logs only: never in what the user (UI, CLAPI, REST) sees.
        error_log(sprintf(
            'Last configuration generation of the monitoring server #%d did not complete (marker %s, started %s)',
            $monitoringServerId,
            $marker,
            $startedBy
        ));

        throw new IncompleteGenerationException(
            sprintf(
                _('The last configuration generation of the monitoring server "%s" did not complete (started %s): it failed or is still running. Nothing has been moved nor restarted, generate its configuration again'),
                $monitoringServerName,
                $startedBy !== '' ? $startedBy : '?',
            ),
            IncompleteGenerationException::REASON
        );
    }

    private function getMarkerPath(int $monitoringServerId): string
    {
        return rtrim($this->generatePath, '/') . '/' . $monitoringServerId . self::MARKER_SUFFIX;
    }
}
