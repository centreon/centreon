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

namespace Core\Media\Infrastructure\Command\MigrateAllMedias;

use Centreon\Domain\Log\LoggerTrait;
use Core\Common\Infrastructure\Command\AbstractMigrationCommand;
use Core\Media\Application\UseCase\MigrateAllMedias\MigrateAllMedias;
use Core\Media\Application\UseCase\MigrateAllMedias\MigrateAllMediasRequest;
use Core\Media\Infrastructure\Repository\ApiWriteMediaRepository;
use Core\Proxy\Application\Repository\ReadProxyRepositoryInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateAllMediasCommand extends AbstractMigrationCommand
{
    use LoggerTrait;

    protected static $defaultName = 'media:all';

    protected static $defaultDescription = 'Migrate all media from the current platform to the defined target platform';

    private int $maxFilesize;

    private int $postMax;

    public function __construct(
        ReadProxyRepositoryInterface $readProxyRepository,
        readonly private ApiWriteMediaRepository $apiWriteMediaRepository,
        readonly private MigrateAllMedias $useCase,
        readonly private int $maxFile,
        string $maxFilesize,
        string $postMax,
    ) {
        parent::__construct($readProxyRepository);
        $this->maxFilesize = self::parseSize($maxFilesize);
        $this->postMax = self::parseSize($postMax);
    }

    protected function configure(): void
    {
        $this->addArgument(
            'target-url',
            InputArgument::REQUIRED,
            "The target platform base URL to connect to the API (ex: 'http://localhost')"
        );
        $this->setHelp(
            "Migrates all media to the target platform.\r\n"
            . 'However the media migration command will not replace media that already exists on the target platform.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->setStyle($output);
            $proxy = $this->getProxy();
            if ($proxy !== null && $proxy !== '') {
                $this->apiWriteMediaRepository->setProxy($proxy);
            }
            if (is_string($target = $input->getArgument('target-url'))) {
                $this->apiWriteMediaRepository->setUrl($target);
            } else {
                // Theoretically it should never happen
                throw new \InvalidArgumentException('target-url is not a string');
            }

            $targetToken = $this->askAuthenticationToken(self::TARGET_PLATFORM, $input, $output);
            $request = new MigrateAllMediasRequest();
            $request->maxFile = $this->maxFile;
            $request->maxFilesize = $this->maxFilesize;
            $request->postMax = $this->postMax;

            $this->apiWriteMediaRepository->setAuthenticationToken($targetToken);
            ($this->useCase)($request, new MigrateAllMediasPresenter($output));
        } catch (\Throwable $ex) {
            $this->writeError($ex->getMessage(), $output);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private static function parseSize(string $size): int
    {
        if ($size === '') {
            return 0;
        }

        $size = mb_strtolower($size);
        $max = (int) ltrim($size, '+');
        switch (mb_substr($size, -1)) {
            case 't':
                $max *= 1024;
            case 'g':
                $max *= 1024;
            case 'm':
                $max *= 1024;
            case 'k':
                $max *= 1024;
        }

        return $max;
    }
}
