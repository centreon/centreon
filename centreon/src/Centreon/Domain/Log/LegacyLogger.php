<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
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

namespace Centreon\Domain\Log;

use Psr\Log\LoggerInterface;

/**
 * This class is designed to be used in legacy codebase to use a logger.
 */
class LegacyLogger implements LoggerInterface
{
    use LoggerTrait {
        emergency as traitEmergency;
        alert as traitAlert;
        critical as traitCritical;
        error as traitError;
        warning as traitWarning;
        notice as traitNotice;
        info as traitInfo;
        debug as traitDebug;
        log as traitLog;
    }

    public function __construct()
    {
        $this->addIgnoreClass(__CLASS__);
    }

    public function emergency($message, array $context = []): void
    {
        $this->traitEmergency((string) $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function alert($message, array $context = []): void
    {
        $this->traitAlert((string) $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function critical($message, array $context = []): void
    {
        $this->traitCritical((string) $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function error($message, array $context = []): void
    {
        $this->traitError((string) $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function warning($message, array $context = []): void
    {
        $this->traitWarning((string) $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function notice($message, array $context = []): void
    {
        $this->traitNotice((string) $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function info($message, array $context = []): void
    {
        $this->traitInfo((string) $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function debug($message, array $context = []): void
    {
        $this->traitDebug((string) $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function log($level, $message, array $context = []): void
    {
        $this->traitLog($level, (string) $message, $context);
    }
}
