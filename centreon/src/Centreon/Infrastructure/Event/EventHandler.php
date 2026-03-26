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
 * This class is for use with the EventDispatcher class.
 *
 * @see EventDispatcher
 * @package Centreon\Domain\Entity
 */
class EventHandler
{
    /**
     * @var array List of callable functions ordered by priority. They will be
     *            loaded before the callable functions defined in the list named 'processing'.
     */
    private $preProcessing = [];

    /**
     * @var array List of callable functions ordered by priority. They will be
     *            loaded before the callable functions defined in the list named
     *            'postProcessing' and after callable functions defined in the list named
     *            'preProcessing'.
     */
    private $processing = [];

    /**
     * @var array List of callable functions ordered by priority. They will be
     *            loaded after the callable functions defined in the list named 'processing'.
     */
    private $postProcessing = [];

    /**
     * @see EventHandler::$preProcessing
     * @return array list of callable functions ordered by priority
     */
    public function getPreProcessing(): array
    {
        return $this->preProcessing;
    }

    /**
     * @param callable $preProcessing Callable function
     * @param int $priority Execution priority of the callable function
     */
    public function setPreProcessing(callable $preProcessing, int $priority = 20): void
    {
        $this->preProcessing[$priority][] = $preProcessing;
    }

    /**
     * @see EventHandler::$processing
     * @return array list of callable functions ordered by priority
     */
    public function getProcessing(): array
    {
        return $this->processing;
    }

    /**
     * @param callable $processing Callable function.
     *                             <code>
     *                             <?php>
     *                             $eventHandler = new EventHandler();
     *                             $eventHandler->setProcessing(
     *                             function(int $eventType, array $arguments, array $executionContext) {...},
     *                             20
     *                             );
     *                             ?>
     *                             <code>
     * @param int $priority Execution priority of the callable function
     */
    public function setProcessing(callable $processing, int $priority = 20): void
    {
        $this->processing[$priority][] = $processing;
    }

    /**
     * @see EventHandler::$postProcessing
     * @return array list of callable functions ordered by priority
     */
    public function getPostProcessing(): array
    {
        return $this->postProcessing;
    }

    /**
     * @param callable $postProcessing Callable function
     * @param int $priority Execution priority of the callable function
     */
    public function setPostProcessing(callable $postProcessing, int $priority = 20): void
    {
        $this->postProcessing[$priority][] = $postProcessing;
    }
}
