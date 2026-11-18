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

namespace Core\Gorgone\Application\Repository;

use Core\Gorgone\Application\Exception\CollectorEnrolmentException;
use Core\Gorgone\Application\Exception\GorgoneCommandException;
use Core\Gorgone\Domain\Model\CollectorEnrolment;
use Core\Gorgone\Domain\Model\CollectorEnrolmentToken;

/**
 * Enrolment tokens and enrolments kept by gorgone-server: it checks the tokens collectors present.
 */
interface CollectorEnrolmentRepositoryInterface
{
    /**
     * Record a token by its hash: gorgone-server never gets the token itself.
     *
     * @param string $id
     * @param string $tokenHash lowercase hex SHA-256 of the token
     * @param int|null $pollerId poller the token enrols (single use), null for a fleet token
     * @param string|null $name
     * @param int $expiresInSeconds
     * @param int|null $maxUses fleet token only, null for no limit
     * @param bool $approvalRequired fleet token only: its enrolments wait for an admin
     *
     * @throws GorgoneCommandException
     */
    public function addToken(
        string $id,
        string $tokenHash,
        ?int $pollerId,
        ?string $name,
        int $expiresInSeconds,
        ?int $maxUses,
        bool $approvalRequired = false,
    ): void;

    /**
     * @throws GorgoneCommandException
     *
     * @return list<CollectorEnrolmentToken> the tokens, without their hash
     */
    public function findTokens(): array;

    /**
     * Revoke a token: it enrols nothing more. The enrolments made with it stay.
     *
     * @param string $tokenId
     *
     * @throws GorgoneCommandException
     *
     * @return bool false when it does not exist
     */
    public function deleteToken(string $tokenId): bool;

    /**
     * Approve a pending enrolment of a token that requires it: the cron then creates its poller.
     *
     * @param string $enrolmentId
     *
     * @throws CollectorEnrolmentException unknown enrolment, or not pending
     * @throws GorgoneCommandException
     */
    public function approve(string $enrolmentId): void;

    /**
     * Reject a pending or approved enrolment: the collector is refused and gets no poller.
     *
     * @param string $enrolmentId
     *
     * @throws CollectorEnrolmentException unknown enrolment, or already bound
     * @throws GorgoneCommandException
     */
    public function reject(string $enrolmentId): void;

    /**
     * Ask the collector of a poller to replace its credential at its next session.
     *
     * @param int $pollerId
     *
     * @throws CollectorEnrolmentException unknown node, or a node without credential
     * @throws GorgoneCommandException
     */
    public function rotateCredential(int $pollerId): void;

    /**
     * @throws GorgoneCommandException
     *
     * @return list<CollectorEnrolment>
     */
    public function findEnrolments(): array;

    /**
     * Give a pending enrolment its poller: the node is created with the credential of the collector.
     *
     * @param string $enrolmentId
     * @param int $pollerId
     * @param string $name
     *
     * @throws GorgoneCommandException
     */
    public function assign(string $enrolmentId, int $pollerId, string $name): void;

    /**
     * @param string $enrolmentId
     *
     * @throws GorgoneCommandException
     */
    public function complete(string $enrolmentId): void;

    /**
     * Connection and configuration state of the nodes: for each node id, whether an agent is
     * connected and whether the engine ever applied a configuration.
     *
     * @throws GorgoneCommandException
     *
     * @return array<int, array{connected: bool, configured: bool}>
     */
    public function findNodeStates(): array;

    /**
     * Delete a node: its credential is revoked. Deleting a node that does not exist is not an error.
     *
     * @param int $pollerId
     *
     * @throws GorgoneCommandException
     */
    public function deleteNode(int $pollerId): void;
}
