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

namespace Core\Gorgone\Infrastructure\Repository;

use Core\Gorgone\Application\Exception\CollectorEnrolmentException;
use Core\Gorgone\Application\Exception\GorgoneCommandException;
use Core\Gorgone\Application\Repository\CollectorEnrolmentRepositoryInterface;
use Core\Gorgone\Domain\Model\CollectorEnrolment;
use Core\Gorgone\Domain\Model\CollectorEnrolmentToken;

/**
 * Enrolment routes of the native REST API ("/v1") of gorgone-server.
 */
final class ApiCollectorEnrolmentRepository extends AbstractApiGorgoneRepository implements CollectorEnrolmentRepositoryInterface
{
    /**
     * @inheritDoc
     */
    public function addToken(
        string $id,
        string $tokenHash,
        ?int $pollerId,
        ?string $name,
        int $expiresInSeconds,
        ?int $maxUses,
        bool $approvalRequired = false,
    ): void {
        $description = sprintf('enrolment token %s', $id);
        $body = array_filter(
            [
                'token_hash' => $tokenHash,
                'node_id' => $pollerId !== null ? (string) $pollerId : null,
                'name' => $name,
                'expires_in_s' => $expiresInSeconds,
                'max_uses' => $maxUses,
                'approval_required' => $approvalRequired ? true : null,
            ],
            static fn (mixed $value): bool => $value !== null
        );
        // Same id and hash again is a no-op: safe to retry.
        [$statusCode, $answer] = $this->call(
            'PUT',
            'enrolment-tokens/' . rawurlencode($id),
            ['json' => $body],
            $description,
            2
        );
        if ($statusCode !== 200 && $statusCode !== 201) {
            throw self::createUnexpectedResponse($description, $statusCode, $answer);
        }
    }

    /**
     * @inheritDoc
     */
    public function findEnrolments(): array
    {
        $description = 'enrolments';
        [$statusCode, $answer] = $this->call('GET', 'enrolments', [], $description, 2);
        if ($statusCode !== 200 || ! is_array($answer['enrolments'] ?? null)) {
            throw self::createUnexpectedResponse($description, $statusCode, $answer);
        }
        $enrolments = [];
        foreach ($answer['enrolments'] as $row) {
            if (! is_array($row) || ! is_string($row['id'] ?? null) || ! is_string($row['state'] ?? null)) {
                continue;
            }
            $nodeId = $row['node_id'] ?? null;
            $enrolments[] = new CollectorEnrolment(
                id: $row['id'],
                state: $row['state'],
                // Nodes the web created are poller ids; any other node is none of its business.
                pollerId: is_string($nodeId) && ctype_digit($nodeId) ? (int) $nodeId : null,
                name: is_string($row['name'] ?? null) ? $row['name'] : '',
                address: is_string($row['address'] ?? null) ? $row['address'] : '',
                agentVersion: is_string($row['agent_version'] ?? null) ? $row['agent_version'] : '',
                tokenId: is_string($row['token_id'] ?? null) ? $row['token_id'] : '',
                // Older servers do not say: their enrolments without node came from fleet tokens.
                isFleet: is_bool($row['fleet'] ?? null) ? $row['fleet'] : true,
                approvalRequired: ($row['approval_required'] ?? false) === true,
                createdAt: is_int($row['created_at'] ?? null) ? $row['created_at'] : 0,
                updatedAt: is_int($row['updated_at'] ?? null) ? $row['updated_at'] : 0,
            );
        }

        return $enrolments;
    }

    /**
     * @inheritDoc
     */
    public function assign(string $enrolmentId, int $pollerId, string $name): void
    {
        $description = sprintf('assignment of enrolment %s to poller #%d', $enrolmentId, $pollerId);
        [$statusCode, $answer] = $this->call(
            'POST',
            'enrolments/' . rawurlencode($enrolmentId) . '/assign',
            ['json' => ['node_id' => (string) $pollerId, 'name' => $name]],
            $description,
            2
        );
        if ($statusCode !== 200) {
            throw self::createUnexpectedResponse($description, $statusCode, $answer);
        }
    }

    /**
     * @inheritDoc
     */
    public function complete(string $enrolmentId): void
    {
        $description = sprintf('completion of enrolment %s', $enrolmentId);
        [$statusCode, $answer] = $this->call(
            'POST',
            'enrolments/' . rawurlencode($enrolmentId) . '/complete',
            [],
            $description,
            2
        );
        if ($statusCode !== 204) {
            throw self::createUnexpectedResponse($description, $statusCode, $answer);
        }
    }

    /**
     * @inheritDoc
     */
    public function findTokens(): array
    {
        $description = 'enrolment tokens';
        [$statusCode, $answer] = $this->call('GET', 'enrolment-tokens', [], $description, 2);
        if ($statusCode !== 200 || ! is_array($answer['tokens'] ?? null)) {
            throw self::createUnexpectedResponse($description, $statusCode, $answer);
        }
        $int = static fn (array $row, string $key): ?int => is_int($row[$key] ?? null) ? $row[$key] : null;
        $tokens = [];
        foreach ($answer['tokens'] as $row) {
            if (! is_array($row) || ! is_string($row['id'] ?? null)) {
                continue;
            }
            $nodeId = $row['node_id'] ?? null;
            $tokens[] = new CollectorEnrolmentToken(
                id: $row['id'],
                pollerId: is_string($nodeId) && ctype_digit($nodeId) ? (int) $nodeId : null,
                name: is_string($row['name'] ?? null) ? $row['name'] : null,
                expiresAt: $int($row, 'expires_at') ?? 0,
                maxUses: $int($row, 'max_uses'),
                uses: $int($row, 'uses') ?? 0,
                createdAt: $int($row, 'created_at') ?? 0,
                approvalRequired: ($row['approval_required'] ?? false) === true,
                lastUsedAt: $int($row, 'last_used_at'),
            );
        }

        return $tokens;
    }

    /**
     * @inheritDoc
     */
    public function deleteToken(string $tokenId): bool
    {
        $description = sprintf('deletion of enrolment token %s', $tokenId);
        [$statusCode, $answer] = $this->call(
            'DELETE',
            'enrolment-tokens/' . rawurlencode($tokenId),
            [],
            $description,
            2
        );
        if ($statusCode === 404) {
            return false;
        }
        if ($statusCode !== 204) {
            throw self::createUnexpectedResponse($description, $statusCode, $answer);
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function approve(string $enrolmentId): void
    {
        $this->decide($enrolmentId, 'approve', sprintf('approval of enrolment %s', $enrolmentId));
    }

    /**
     * @inheritDoc
     */
    public function reject(string $enrolmentId): void
    {
        $this->decide($enrolmentId, 'reject', sprintf('rejection of enrolment %s', $enrolmentId));
    }

    /**
     * @inheritDoc
     */
    public function rotateCredential(int $pollerId): void
    {
        $description = sprintf('credential rotation of node %d', $pollerId);
        [$statusCode, $answer] = $this->call('POST', 'nodes/' . $pollerId . '/rotate-credential', [], $description);
        match ($statusCode) {
            200, 202, 204 => null,
            404 => throw CollectorEnrolmentException::notFound(
                sprintf('Poller #%d has no collector in gorgone-server', $pollerId)
            ),
            409 => throw CollectorEnrolmentException::conflict(
                self::message($answer, 'the collector has no credential to rotate')
            ),
            default => throw self::createUnexpectedResponse($description, $statusCode, $answer),
        };
    }

    /**
     * @inheritDoc
     */
    public function findNodeStates(): array
    {
        $description = 'nodes';
        [$statusCode, $answer] = $this->call('GET', 'nodes', [], $description, 2);
        if ($statusCode !== 200 || ! is_array($answer['nodes'] ?? null)) {
            throw self::createUnexpectedResponse($description, $statusCode, $answer);
        }
        $states = [];
        foreach ($answer['nodes'] as $node) {
            $id = is_array($node) ? ($node['id'] ?? null) : null;
            if (! is_string($id) || ! ctype_digit($id)) {
                continue;
            }
            $applied = $node['applied'] ?? null;
            $states[(int) $id] = [
                'connected' => ($node['connected'] ?? false) === true,
                // The engine reports the digest of the configuration it applied.
                'configured' => is_array($applied)
                    && is_string($applied['engine'] ?? null)
                    && $applied['engine'] !== '',
            ];
        }

        return $states;
    }

    /**
     * @inheritDoc
     */
    public function deleteNode(int $pollerId): void
    {
        $description = sprintf('deletion of node %d', $pollerId);
        [$statusCode, $answer] = $this->call('DELETE', 'nodes/' . $pollerId, [], $description, 2);
        if ($statusCode !== 204 && $statusCode !== 404) {
            throw self::createUnexpectedResponse($description, $statusCode, $answer);
        }
    }

    /**
     * @param string $enrolmentId
     * @param 'approve'|'reject' $decision
     * @param string $description
     *
     * @throws CollectorEnrolmentException
     * @throws GorgoneCommandException
     */
    private function decide(string $enrolmentId, string $decision, string $description): void
    {
        [$statusCode, $answer] = $this->call(
            'POST',
            'enrolments/' . rawurlencode($enrolmentId) . '/' . $decision,
            [],
            $description,
            2
        );
        match ($statusCode) {
            200, 204 => null,
            404 => throw CollectorEnrolmentException::notFound(sprintf('Unknown enrolment %s', $enrolmentId)),
            409 => throw CollectorEnrolmentException::conflict(
                self::message($answer, sprintf('The enrolment cannot be %sd', $decision))
            ),
            default => throw self::createUnexpectedResponse($description, $statusCode, $answer),
        };
    }

    /**
     * @param array<mixed> $answer
     * @param string $default
     */
    private static function message(array $answer, string $default): string
    {
        return is_string($answer['message'] ?? null) && $answer['message'] !== '' ? $answer['message'] : $default;
    }
}
