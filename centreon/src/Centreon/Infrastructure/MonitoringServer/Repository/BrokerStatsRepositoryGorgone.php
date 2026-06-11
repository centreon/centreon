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

namespace Centreon\Infrastructure\MonitoringServer\Repository;

use Centreon\Domain\Gorgone\Command\Command;
use Centreon\Domain\Gorgone\GorgoneException;
use Centreon\Domain\Gorgone\Interfaces\GorgoneServiceInterface;
use Centreon\Domain\MonitoringServer\Interfaces\BrokerStatsRepositoryInterface;

/**
 * Reads a poller's broker statistics file through Gorgone's action module: it asks the target node to
 * "cat" the "-stats.json" file (POST nodes/{id}/core/action/command) and reads the command result
 * (stdout) back from the asynchronous token log. The web tier therefore needs neither the local
 * broker-stats cache nor a shared filesystem.
 *
 * Note: the "cat" command must be allowed by the node's Gorgone whitelist. The default whitelist only
 * covers "/var/lib/centreon-engine/*-stats.json"; broker stats stored elsewhere (e.g.
 * "/var/lib/centreon-broker") require an explicit whitelist entry on the collection node.
 */
final class BrokerStatsRepositoryGorgone implements BrokerStatsRepositoryInterface
{
    /** Gorgone log code carrying a finished command's result (stdout/exit_code). */
    private const COMMAND_RESULT_CODE = 100;

    /** Number of times the token log is polled before giving up. */
    private const MAX_POLLS = 15;

    /** Delay between two polls, in microseconds. */
    private const POLL_INTERVAL_US = 300000;

    public function __construct(
        private readonly GorgoneServiceInterface $gorgoneService,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getStatsContent(int $pollerId, string $statsFilePath): ?string
    {
        $body = json_encode([['command' => 'cat ' . $statsFilePath]]);
        if ($body === false) {
            throw new GorgoneException(_('Unable to encode the broker statistics command for Gorgone'));
        }

        $command = new Command($pollerId, $body);

        try {
            $response = $this->gorgoneService->send($command);
            $token = $response->getCommand()?->getToken();
            if ($token === null || $token === '') {
                throw new GorgoneException(_('Gorgone did not return a token for the broker statistics command'));
            }

            for ($poll = 0; $poll < self::MAX_POLLS; $poll++) {
                usleep(self::POLL_INTERVAL_US);

                $logResponse = $this->gorgoneService->getResponseFromToken($pollerId, $token);
                foreach ($logResponse->getActionLogs() as $log) {
                    if ($log->getCode() !== self::COMMAND_RESULT_CODE) {
                        continue;
                    }

                    $data = json_decode($log->getData(), true);
                    $exitCode = $data['result']['exit_code'] ?? null;
                    $stdout = $data['result']['stdout'] ?? null;

                    if ($exitCode !== 0 || ! is_string($stdout)) {
                        throw new GorgoneException(
                            sprintf(
                                _('Could not read broker statistics "%s" on poller %d (exit code %s)'),
                                $statsFilePath,
                                $pollerId,
                                var_export($exitCode, true)
                            )
                        );
                    }

                    return $stdout;
                }
            }
        } catch (GorgoneException $ex) {
            throw $ex;
        } catch (\Throwable $ex) {
            throw new GorgoneException(
                sprintf(
                    _('Error while retrieving broker statistics for poller %d through Gorgone: %s'),
                    $pollerId,
                    $ex->getMessage()
                ),
                (int) $ex->getCode(),
                $ex
            );
        }

        // No result log arrived within the polling window.
        return null;
    }
}
