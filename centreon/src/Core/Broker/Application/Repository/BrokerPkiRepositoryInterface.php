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

namespace Core\Broker\Application\Repository;

use Core\Broker\Domain\Model\PkiCertificate;

/**
 * Certificates of the managed PKI, one per subject, shared by every web replica: writes are conditional so that two
 * replicas issuing at once keep one certificate.
 */
interface BrokerPkiRepositoryInterface
{
    /**
     * @throws \Throwable
     */
    public function find(string $subject): ?PkiCertificate;

    /**
     * Stores a certificate for a subject that has none.
     *
     * @throws \Throwable
     *
     * @return bool false when the subject already has one (another replica stored it first): nothing is written
     */
    public function add(PkiCertificate $certificate): bool;

    /**
     * Replaces the certificate of a subject if it is still the one with $previousFingerprint.
     *
     * @throws \Throwable
     *
     * @return bool false when it changed meanwhile: nothing is written
     */
    public function replace(PkiCertificate $certificate, string $previousFingerprint): bool;
}
