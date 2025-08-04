<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Tests\Centreon\Domain\Log;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\ContactForDebug;
use Centreon\Domain\Log\LoggerTrait;
use Mockery;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

/**
 * Class
 *
 * @class LoggerStub
 * @package Tests\Centreon\Domain\Log
 */
class LoggerStub implements LoggerInterface {
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
    /** @var Logger */
    private Logger $monolog;
    /** @var string */
    private string $logPathFileName;

    /**
     * LoggerStub constructor
     *
     * @param string $logPathFileName
     */
    public function __construct(string $logPathFileName)
    {
        $this->monolog = new Logger('test_logger');
        $this->monolog->pushHandler(new StreamHandler($logPathFileName));
        $this->setLogger($this->monolog);
        $this->loggerContact = Mockery::mock(ContactInterface::class);
        $this->loggerContactForDebug = Mockery::mock(ContactForDebug::class);
        $this->loggerContactForDebug->shouldReceive('isValidForContact')->andReturnTrue();
    }

    /**
     * Factory
     *
     * @param string $logPathFileName
     *
     * @return LoggerInterface
     */
    public static function create(string $logPathFileName): LoggerInterface
    {
        return new self($logPathFileName);
    }

    /**
     * @inheritDoc
     */
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
