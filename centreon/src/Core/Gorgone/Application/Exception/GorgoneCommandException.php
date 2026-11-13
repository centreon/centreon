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

namespace Core\Gorgone\Application\Exception;

final class GorgoneCommandException extends \Exception
{
    public static function featureDisabled(string $featureFlag): self
    {
        return new self(sprintf(
            'Commands cannot be sent through the Gorgone API: feature flag "%s" is disabled',
            $featureFlag
        ));
    }

    public static function invalidCommandLine(string $line): self
    {
        return new self(sprintf('Invalid Gorgone command line "%s"', $line));
    }

    public static function unsupportedCommand(string $command): self
    {
        return new self(sprintf('Command "%s" is not supported by the Gorgone API', $command));
    }

    public static function unknownPoller(string $poller): self
    {
        return new self(sprintf('Poller "%s" does not exist', $poller));
    }

    public static function unknownNode(int $nodeId): self
    {
        return new self(sprintf('Poller #%d is not known by Gorgone', $nodeId));
    }

    public static function missingConfiguration(int $nodeId, string $target): self
    {
        return new self(sprintf(
            'No "%s" configuration file found for poller #%d. Please generate the configuration first',
            $target,
            $nodeId
        ));
    }

    public static function invalidConfigurationFile(int $nodeId, string $target, \Throwable $previous): self
    {
        return new self(
            sprintf(
                'Cannot build the "%s" configuration archive of poller #%d: %s',
                $target,
                $nodeId,
                $previous->getMessage()
            ),
            previous: $previous
        );
    }

    public static function missingApiAddress(): self
    {
        return new self('The address of the Gorgone API is not configured');
    }

    public static function requestFailed(string $description, \Throwable $previous): self
    {
        return new self(
            sprintf('Request to the Gorgone API failed (%s): %s', $description, $previous->getMessage()),
            previous: $previous
        );
    }

    public static function unexpectedResponse(string $description, int $statusCode, string $detail): self
    {
        return new self(sprintf(
            'Gorgone API answered HTTP %d (%s)%s',
            $statusCode,
            $description,
            $detail !== '' ? ': ' . $detail : ''
        ));
    }
}
