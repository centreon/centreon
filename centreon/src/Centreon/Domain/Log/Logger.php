<?php

/*
 * Copyright 2005 - 2021 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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
use Centreon\Domain\Log\LoggerTrait;

/**
 * This class is designed to be used in legacy codebase to use a logger
 *
 * @package Centreon\Domain\Log
 */
class Logger implements LoggerInterface
{
    public const LEVEL_OFF = 900;

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

    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->traitEmergency($message, $context);
    }

    /**
     * @inheritDoc
     */
    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->traitAlert($message, $context);
    }

    /**
     * @inheritDoc
     */
    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->traitCritical($message, $context);
    }

    /**
     * @inheritDoc
     */
    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->traitError($message, $context);
    }

    /**
     * @inheritDoc
     */
    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->traitWarning($message, $context);
    }

    /**
     * @inheritDoc
     */
    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->traitNotice($message, $context);
    }

    /**
     * @inheritDoc
     */
    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->traitInfo($message, $context);
    }

    /**
     * @inheritDoc
     */
    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->traitDebug($message, $context);
    }

    /**
     * @inheritDoc
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->traitLog($level, $message, $context);
    }
}
