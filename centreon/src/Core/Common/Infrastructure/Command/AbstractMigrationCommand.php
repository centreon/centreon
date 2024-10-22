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

namespace Core\Common\Infrastructure\Command;

use Assert\AssertionFailedException;
use Core\Common\Infrastructure\Command\Exception\MigrationCommandException;
use Core\Proxy\Application\Repository\ReadProxyRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

abstract class AbstractMigrationCommand extends Command
{
    public const LOCAL_PLATFORM = 0;
    public const TARGET_PLATFORM = 1;

    private static ?string $proxy = null;

    private static bool $isProxyAlreadyLoaded = false;

    public function __construct(readonly private ReadProxyRepositoryInterface $readProxyRepository)
    {
        parent::__construct();
    }

    protected function setStyle(OutputInterface $output): void
    {
        $output->getFormatter()->setStyle(
            'error',
            new OutputFormatterStyle('red', '', ['bold'])
        );

    }

    /**
     * @param int $platform self::LOCAL_PLATFORM|self::TARGET_PLATFORM
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @throws MigrationCommandException
     *
     * @return string
     */
    protected function askAuthenticationToken(int $platform, InputInterface $input, OutputInterface $output): string
    {
        $question = match ($platform) {
            self::LOCAL_PLATFORM => 'Local authentication token? ',
            self::TARGET_PLATFORM => 'Target authentication token? ',
            default => throw new \InvalidArgumentException('Please choose an available platform type')
        };

        return $this->askQuestion($question, $input, $output);
    }

    protected function writeError(string $message, OutputInterface $output): void
    {
        $output->writeln("<error>{$message}</error>");
    }

    /**
     *  **Available formats:**.
     *
     *  <<procotol>>://<<user>>:<<password>>@<<url>>:<<port>>
     *
     *  <<procotol>>://<<user>>:<<password>>@<<url>>
     *
     *  <<procotol>>://<<url>>:<<port>>
     *
     *  <<procotol>>://<<url>>
     *
     * @throws AssertionFailedException
     *
     * @return string|null
     */
    protected function getProxy(): ?string
    {
        if (! self::$isProxyAlreadyLoaded) {
            $proxy = $this->readProxyRepository->getProxy();

            self::$proxy = $proxy ? (string) $proxy : null;
        }
        self::$isProxyAlreadyLoaded = true;

        return self::$proxy;
    }

    /**
     * @param string $message Question to display
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @throws MigrationCommandException
     * @throws \Exception
     *
     * @return string Response
     */
    protected function askQuestion(string $message, InputInterface $input, OutputInterface $output): string
    {
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $tokenQuestion = new Question($message, '');
        $tokenQuestion->setHidden(true);
        /** @var string $response */
        $response = $helper->ask($input, $output, $tokenQuestion);

        if ($response === '') {
            throw MigrationCommandException::tokenCannotBeEmpty();
        }

        return $response;
    }
}
