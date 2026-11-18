<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

namespace Core\RemoteChannel\Infrastructure\Broker;

use Core\RemoteChannel\Application\PollerCommandSenderInterface;
use Core\RemoteChannel\Application\Repository\ReadPollerTargetRepositoryInterface;
use Core\RemoteChannel\Domain\Exception\RemoteChannelException;
use Core\RemoteChannel\Domain\Model\DispatchStatus;
use Core\RemoteChannel\Domain\Model\PollerTarget;

/**
 * Sends external commands through the central broker remote API (POST /v1/commands, synchronous).
 * Every poller of the call is sent in one request; the answer tells, per poller, whether the engine applied them.
 */
final class BrokerPollerCommandSender implements PollerCommandSenderInterface
{
    /** Budget given to cbd for the whole request (contracts.md: default 10 s, max 30 s) */
    private const COMMAND_TIMEOUT_MS = 10000;

    /** HTTP timeout: cbd answers within COMMAND_TIMEOUT_MS, plus margin. Stays under the 30 s time-to-error. */
    private const HTTP_TIMEOUT = 15.0;

    public function __construct(
        private readonly BrokerApiClient $client,
        private readonly ReadPollerTargetRepositoryInterface $pollerTargetRepository,
    ) {
    }

    public function send(array $batches): void
    {
        $items = [];
        /** @var array<string, PollerTarget> $targetsByUid */
        $targetsByUid = [];
        foreach ($batches as ['target' => $target, 'commands' => $commands]) {
            $sanitized = array_values(array_filter(
                array_map($this->sanitize(...), $commands),
                static fn (string $command): bool => $command !== ''
            ));
            if ($sanitized === []) {
                continue;
            }
            $targetsByUid[$target->uid] = $target;
            $items[] = ['poller_id' => $target->uid, 'commands' => $sanitized];
        }
        if ($items === []) {
            return;
        }

        $response = $this->client->call(
            'POST',
            '/v1/commands',
            [
                'request_id' => self::generateRequestId(),
                'timeout_ms' => self::COMMAND_TIMEOUT_MS,
                'items' => $items,
            ],
            self::HTTP_TIMEOUT
        );

        $failures = [];
        $answered = [];
        foreach (is_array($response['items'] ?? null) ? $response['items'] : [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $uid = (string) ($item['poller_id'] ?? '');
            $answered[$uid] = true;
            $status = DispatchStatus::tryFrom((string) ($item['status'] ?? '')) ?? DispatchStatus::Timeout;
            if (! $status->isFailure()) {
                continue;
            }
            $failures[] = RemoteChannelException::forPoller(
                $targetsByUid[$uid]->name ?? $uid,
                $status,
                self::describe($item)
            );
        }
        foreach ($targetsByUid as $uid => $target) {
            if (! isset($answered[$uid])) {
                $failures[] = RemoteChannelException::forPoller($target->name, DispatchStatus::Timeout, 'no answer');
            }
        }

        if ($failures !== []) {
            throw self::combine($failures);
        }
    }

    /**
     * Same protection as Gorgone legacycmd (illegal characters option) plus one command per line.
     */
    private function sanitize(string $command): string
    {
        $command = str_replace(["\r", "\n"], '', $command);
        $illegalCharacters = $this->pollerTargetRepository->findIllegalCharacters();
        if ($illegalCharacters !== '') {
            $command = str_replace(mb_str_split($illegalCharacters), '', $command);
        }

        return trim($command);
    }

    /**
     * @param array<mixed> $item
     */
    private static function describe(array $item): string
    {
        $messages = [];
        if (is_string($item['message'] ?? null) && $item['message'] !== '') {
            $messages[] = $item['message'];
        }
        foreach (is_array($item['results'] ?? null) ? $item['results'] : [] as $result) {
            if (
                is_array($result)
                && ($result['status'] ?? '') !== DispatchStatus::Applied->value
                && is_string($result['message'] ?? null)
                && $result['message'] !== ''
            ) {
                $messages[] = sprintf('#%d %s', (int) ($result['index'] ?? 0), $result['message']);
            }
        }

        return implode('; ', array_unique($messages));
    }

    /**
     * @param non-empty-list<RemoteChannelException> $failures
     */
    private static function combine(array $failures): RemoteChannelException
    {
        if (count($failures) === 1) {
            return $failures[0];
        }

        return new RemoteChannelException(
            implode(' | ', array_map(static fn (\Throwable $ex): string => $ex->getMessage(), $failures)),
            $failures[0]->status,
            $failures[0]
        );
    }

    public static function generateRequestId(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
