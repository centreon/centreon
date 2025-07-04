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

namespace Centreon\Infrastructure\Event;

/**
 * Class EventDispatcher
 *
 * @see EventHandler
 * @package Centreon\Domain\Entity
 */
class EventDispatcher
{
    /**
     * Event types
     */
    public const EVENT_ADD     = 1;
    public const EVENT_UPDATE  = 2;
    public const EVENT_DELETE  = 4;
    public const EVENT_READ    = 8;
    public const EVENT_DISPLAY = 16;
    public const EVENT_DUPLICATE = 32;
    public const EVENT_SYNCHRONIZE = 64;

    /**
     * @var array List a values returned by callable function defined in the
     *            event handler. Their values are partitioned by context name.
     */
    private $executionContext = [];

    /** @var mixed[] */
    private $eventHandlers;

    /** @var array sorted list of methods that will be called in the event handler */
    private $eventMethods = ['preProcessing', 'processing', 'postProcessing'];

    /** @var DispatcherLoaderInterface */
    private $dispatcherLoader;

    /**
     * @return DispatcherLoaderInterface
     */
    public function getDispatcherLoader(): ?DispatcherLoaderInterface
    {
        return $this->dispatcherLoader;
    }

    /**
     * @param DispatcherLoaderInterface $dispatcherLoader loader that will be
     *                                                    used to include PHP files in which we add event handlers
     */
    public function setDispatcherLoader(DispatcherLoaderInterface $dispatcherLoader): void
    {
        $this->dispatcherLoader = $dispatcherLoader;
    }

    /**
     * Add a new event handler which will be called by the method 'notify'
     *
     * @see EventDispatcher::notify()
     * @param string $context Name of the context in which we add the event handler
     * @param int $eventType Event type
     * @param EventHandler $eventHandler Event handler to add
     */
    public function addEventHandler(string $context, int $eventType, EventHandler $eventHandler): void
    {
        foreach ($this->eventMethods as $eventMethod) {
            $methodName = 'get' . ucfirst($eventMethod);
            foreach (call_user_func([$eventHandler, $methodName]) as $priority => $callables) {
                $this->eventHandlers[$context][$eventType][$eventMethod][$priority] = array_merge(
                    $this->eventHandlers[$context][$eventType][$eventMethod][$priority] ?? [],
                    $callables
                );
            }
        }
    }

    /**
     * Notify all event handlers for a specific context and type of event.
     *
     * @param string $context name of the context in which we will call all the
     *                        registered event handlers
     * @param int $eventType Event type. Only event handlers registered for this event will be called.
     *                       We can add several types of events using the binary operator '|'
     * @param array $arguments Array of arguments that will be passed to callable
     *                         functions defined in event handlers
     */
    public function notify(string $context, int $eventType, $arguments = []): void
    {
        $sortedCallables = $this->getSortedCallables($context, $eventType);

        /*
         * Pay attention,
         * The order of this loop is important because we have to call the
         * callable functions in this precise order
         * "pre-processing", "processing" and "post-processing".
         */
        foreach ($this->eventMethods as $eventMethod) {
            if (isset($sortedCallables[$eventMethod])) {
                /*
                 * We will call all the callable functions defined in order of priority
                 * (from the lowest priority to the highest)
                 * All results returned by the callable functions will be saved
                 * in the execution context and isolated by the context name.
                 */
                foreach ($sortedCallables[$eventMethod] as $priority => $callables) {
                    foreach ($callables as $callable) {
                        $currentExecutionContext
                            = $this->executionContext[$context][$eventType] ?? [];
                        $result = call_user_func_array(
                            $callable,
                            [$arguments, $currentExecutionContext, $eventType]
                        );
                        if (isset($result)) {
                            $this->executionContext[$context][$eventType]
                                = array_merge(
                                    $this->executionContext[$context][$eventType] ?? [],
                                    $result
                                );
                        }
                    }
                }
            }
        }
    }

    /**
     * Retrieve all callable functions sorted by method and priority for a
     * specific context and event type.
     *
     * @param string $context name of the context
     * @param int $eventType event type
     * @return array List of partitioned event handlers by processing method
     *               ('preProcessing', 'processing', 'postProcessing') and processing priority
     */
    private function getSortedCallables(string $context, int $eventType): array
    {
        $sortedCallables = [];
        if (isset($this->eventHandlers[$context])) {
            foreach ($this->eventHandlers[$context] as $contextEventType => $callablesSortedByMethod) {
                if ($contextEventType & $eventType) {
                    foreach ($callablesSortedByMethod as $method => $callablesSortedByPriority) {
                        foreach ($callablesSortedByPriority as $priority => $callables) {
                            $sortedCallables[$method][$priority] = array_merge_recursive(
                                $sortedCallables[$method][$priority] ?? [],
                                $callables
                            );
                            /*
                             * It is important to sort from the lowest priority
                             * to the highest because the callable function with
                             * the lowest priority will be called first.
                             */
                            ksort($sortedCallables[$method]);
                        }
                    }
                }
            }
        }

        return $sortedCallables;
    }
}
