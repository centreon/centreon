<?php
/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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
 *  For more information : contact@centreon.com
 */

namespace Core\Security\ProviderConfiguration\Infrastructure\Logger;

use CentreonUserLog;
use Core\Security\ProviderConfiguration\Domain\LoginLoggerInterface;
use Pimple\Container;

class LoginLogger implements LoginLoggerInterface
{
    /**
     * @var CentreonUserLog
     */
    private CentreonUserLog $logger;

    /**
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $pearDB = $container['configuration_db'];
        $this->logger = new CentreonUserLog(-1, $pearDB);
    }

    /**
     * @inheritDoc
     */
    public function debug(string $scope, string $message, array $content = []): void
    {
        $this->logger->insertLog(
            CentreonUserLog::TYPE_LOGIN,
            "[$scope] [DEBUG] $message " . json_encode($content)
        );
    }

    /**
     * @inheritDoc
     */
    public function info(string $scope, string $message, array $content = []): void
    {
        $this->logger->insertLog(
            CentreonUserLog::TYPE_LOGIN,
            "[$scope] [INFO] $message " . json_encode($content)
        );
    }

    /**
     * @inheritDoc
     */
    public function error(string $scope, string $message, array $content = []): void
    {
        if (array_key_exists('error', $content)) {
            $this->logger->insertLog(
                CentreonUserLog::TYPE_LOGIN,
                "[$scope] [Error] $message" . json_encode($content)
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function exception(string $scope, string $message, \Exception $exception): void
    {
        $this->logger->insertLog(
            CentreonUserLog::TYPE_LOGIN,
            sprintf(
                "[$scope] [ERROR] $message",
                get_class($exception),
                $exception->getMessage()
            )
        );
    }

}
