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

namespace App\Shared\Infrastructure\Messenger;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Workaround for the Symfony 8.1.0 messenger regression symfony/symfony#64529:
 * the per-bus `default_middleware: allow_no_handlers` flag is silently dropped, so
 * `event.bus` throws NoHandlerForMessageException when dispatching an event that has
 * no handler (the common case for our domain events).
 *
 * The FrameworkBundle extension appends the configured flag as a numeric argument on
 * the per-bus `handle_message` middleware instead of replacing it, so it never reaches
 * HandleMessageMiddleware::$allowNoHandlers (constructor argument index 1), which keeps
 * its default `false`. We force that argument back to `true` for the event bus.
 *
 * @see https://github.com/symfony/symfony/issues/64529
 *
 * Remove this pass (and its registration in {@see \App\Shared\Infrastructure\Symfony\Kernel})
 * once a Symfony release ships the upstream fix.
 */
final class AllowNoHandlersOnEventBusPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $handleMessageId = 'event.bus.middleware.handle_message';
        if ($container->hasDefinition($handleMessageId)) {
            $container->getDefinition($handleMessageId)->replaceArgument(1, true);
        }
    }
}
