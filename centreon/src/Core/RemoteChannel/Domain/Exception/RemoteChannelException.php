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

namespace Core\RemoteChannel\Domain\Exception;

use Core\RemoteChannel\Domain\Model\DispatchStatus;

/**
 * A command or a deployment did not reach (or was refused by) a poller. The message names the poller and is
 * meant for the API client as is. The code is the HTTP status matching the reason.
 */
class RemoteChannelException extends \Exception
{
    public function __construct(
        string $message,
        public readonly ?DispatchStatus $status = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $status?->httpStatus() ?? 502, $previous);
    }

    public static function forPoller(string $pollerName, DispatchStatus $status, string $detail = ''): self
    {
        $message = match ($status) {
            DispatchStatus::NotConnected => sprintf(
                _('Monitoring server "%s" is not connected to the central broker: nothing has been sent'),
                $pollerName
            ),
            DispatchStatus::PeerTooOld => sprintf(
                _('Monitoring server "%s" does not accept remote actions (old version or remote_actions disabled)'),
                $pollerName
            ),
            DispatchStatus::Rejected => sprintf(
                _('Monitoring server "%s" rejected the request'),
                $pollerName
            ),
            DispatchStatus::Timeout => sprintf(
                _('Monitoring server "%s" did not confirm the request in time, its outcome is unknown'),
                $pollerName
            ),
            default => sprintf(_('Unexpected status %s for monitoring server "%s"'), $status->value, $pollerName),
        };

        return new self($detail !== '' ? $message . ': ' . $detail : $message, $status);
    }

    public static function brokerUnreachable(string $detail, ?\Throwable $previous = null): self
    {
        return new self(
            sprintf(_('The central broker remote API could not be reached: %s'), $detail),
            null,
            $previous
        );
    }

    public static function unknownPoller(int|string $pollerKey): self
    {
        return new self(sprintf(_('Unknown monitoring server %s'), (string) $pollerKey), DispatchStatus::Rejected);
    }
}
