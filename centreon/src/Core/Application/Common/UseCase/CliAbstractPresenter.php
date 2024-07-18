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

namespace Core\Application\Common\UseCase;

use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\OutputInterface;

abstract class CliAbstractPresenter
{
    public function __construct(readonly private OutputInterface $output)
    {
        $this->output->getFormatter()->setStyle(
            'error',
            new OutputFormatterStyle('red', '', ['bold'])
        );
        $this->output->getFormatter()->setStyle(
            'ok',
            new OutputFormatterStyle('green', '', ['bold'])
        );
        $this->output->getFormatter()->setStyle(
            'warning',
            new OutputFormatterStyle('', '#FFA500', ['bold'])
        );
    }

    public function error(string $message): void
    {
        $this->output->writeln("<error>{$message}</>");
    }

    public function ok(string $message): void
    {
        $this->output->writeln("<ok>{$message}</>");
    }

    public function warning(string $message): void
    {
        $this->output->writeln("<warning>{$message}</>");
    }

    public function write(string $message): void
    {
        $this->output->writeln($message);
    }

    /**
     * @param string[] $messages
     */
    public function writeMultiLine(array $messages): void
    {
        $this->output->writeln($messages);
    }
}
