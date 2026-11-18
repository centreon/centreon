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

use Core\Gorgone\Application\Repository\CollectorEnrolmentRepositoryInterface;
use Core\Gorgone\Domain\Model\CollectorEnrolment;

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
    ): void {
        $description = sprintf('enrolment token %s', $id);
        $body = array_filter(
            [
                'token_hash' => $tokenHash,
                'node_id' => $pollerId !== null ? (string) $pollerId : null,
                'name' => $name,
                'expires_in_s' => $expiresInSeconds,
                'max_uses' => $maxUses,
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
}
