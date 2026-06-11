<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Centreon\Domain\Gorgone;

use Centreon\Domain\Option\Interfaces\OptionServiceInterface;

/**
 * Decides whether engine/poller commands (external commands, config deploy, reload/restart) are
 * sent through the Gorgone REST API or written to the local centcore pipe.
 *
 * Controlled by the "gorgone_command_transport" option: 'gorgone' enables the REST transport
 * (web tier separate from the collection stack); anything else (default, including when the option
 * is absent) keeps the legacy centcore transport, so existing collocated/monolithic setups are
 * unaffected.
 */
class GorgoneTransport
{
    public const OPTION_KEY = 'gorgone_command_transport';
    public const TRANSPORT_GORGONE = 'gorgone';

    /** @var bool|null */
    private $useGorgone;

    public function __construct(
        private readonly OptionServiceInterface $optionService,
    ) {
    }

    /**
     * @throws \Throwable
     */
    public function useGorgone(): bool
    {
        if ($this->useGorgone === null) {
            $this->useGorgone = false;
            foreach ($this->optionService->findSelectedOptions([self::OPTION_KEY]) as $option) {
                if ($option->getName() === self::OPTION_KEY) {
                    $this->useGorgone = ($option->getValue() === self::TRANSPORT_GORGONE);
                }
            }
        }

        return $this->useGorgone;
    }
}
