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

namespace Core\TimePeriod\Infrastructure\API\AddTimePeriod;

use Core\TimePeriod\Application\UseCase\AddTimePeriod\AddTimePeriodRequest;
use Core\TimePeriod\Application\UseCase\AddTimePeriod\DtoException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(name: 'app-test', description: 'Add a new time period')]
class AddTimePeriodCommand extends Command
{
    private ValidatorInterface $validator;

    public function __construct(ValidatorInterface $validator)
    {
        parent::__construct();
        $this->validator = $validator;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $object = new AddTimePeriodRequest(
            11,
            true,
            [
                ['day' => '1', 'time_range' => '00:00-23:59'],
                ['day' => 2, 'time_range' => '00:00-23:59'],
                ['day' => 3, 'time_range' => '00:00-23:59'],
                ['day' => 4, 'time_range' => '00:00-23:59'],
                ['day' => 5, 'time_range' => '00:00-23:59'],
                ['day' => 6, 'time_range' => '00:00-23:59'],
                ['day' => 7, 'time_range' => '00:00-23:59'],
            ],
            [1, 2, 3],
            [new DtoException('monday 1', '06:00-07:00')]
        );

        $errors = $this->validator->validate($object);
        dump($errors);

        return Command::SUCCESS;
    }
}
