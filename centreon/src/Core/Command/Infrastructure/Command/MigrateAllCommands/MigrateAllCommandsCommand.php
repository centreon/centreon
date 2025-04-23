<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

declare(strict_types = 1);

namespace Core\Command\Infrastructure\Command\MigrateAllCommands;

use Centreon\Domain\Log\LoggerTrait;
use Core\Command\Application\UseCase\MigrateAllCommands\MigrateAllCommands;
use Core\Command\Infrastructure\Repository\ApiWriteCommandRepository;
use Core\Common\Infrastructure\Command\AbstractMigrationCommand;
use Core\Proxy\Application\Repository\ReadProxyRepositoryInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateAllCommandsCommand extends AbstractMigrationCommand
{
    use LoggerTrait;

    protected static $defaultName = 'command:all';

    protected static $defaultDescription = 'Migrate all commands from the current platform to the defined target platform';

    public function __construct(
        ReadProxyRepositoryInterface $readProxyRepository,
        readonly private ApiWriteCommandRepository $apiWriteCommandRepository,
        readonly private MigrateAllCommands $useCase,
        private readonly int $curlTimeout,
    ) {
        parent::__construct($readProxyRepository);
    }

    protected function configure(): void
    {
        $this->addArgument(
            'target-url',
            InputArgument::REQUIRED,
            "The target platform base url to connect to the API (ex: 'http://localhost/centreon')"
        );
        $this->setHelp(
            "Migrates all commands to the target platform.\r\n"
            . 'However the commands migration command will not replace commands that already exists on the target platform.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->setStyle($output);

            $this->apiWriteCommandRepository->setTimeout($this->curlTimeout);

            $proxy = $this->getProxy();
            if ($proxy !== null && $proxy !== '') {
                $this->apiWriteCommandRepository->setProxy($proxy);
            }
            if (is_string($target = $input->getArgument('target-url'))) {
                $this->apiWriteCommandRepository->setUrl($target);
            } else {
                // Theoretically it should never happen
                throw new \InvalidArgumentException('target-url is not a string');
            }
            $token = $this->askAuthenticationToken(self::TARGET_PLATFORM, $input, $output);

            $this->apiWriteCommandRepository->setAuthenticationToken($token);
            ($this->useCase)(new MigrateAllCommandsPresenter($output));
        } catch (\Throwable $ex) {
            $this->writeError($ex->getMessage(), $output);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
