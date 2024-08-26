<?php declare(strict_types=1);

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

namespace CentreonLegacy\Core\Install;

class Information
{
    /** @var \Pimple\Container */
    protected $dependencyInjector;

    /**
     * @param \Pimple\Container $dependencyInjector
     */
    public function __construct(\Pimple\Container $dependencyInjector)
    {
        $this->dependencyInjector = $dependencyInjector;
    }

    public function getStep()
    {
        $step = 1;

        $stepFile = __DIR__ . '/../../../../www/install/tmp/step.json';
        if ($this->dependencyInjector['filesystem']->exists($stepFile)) {
            $content = json_decode(file_get_contents($stepFile), true);
            if (isset($content['step'])) {
                $step = $content['step'];
            }
        }

        return $step;
    }

    public function setStep($step): void
    {
        $stepDir = __DIR__ . '/../../../../www/install/tmp';
        if (! $this->dependencyInjector['filesystem']->exists($stepDir)) {
            $this->dependencyInjector['filesystem']->mkdir($stepDir);
        }

        $stepFile = $stepDir . '/step.json';
        file_put_contents($stepFile, json_encode([
            'step' => $step,
        ]));
    }

    public function getStepContent()
    {
        $content = '';

        $step = $this->getStep();

        $className = '\CentreonLegacy\Core\Install\Step\Step' . $step;
        if (class_exists($className)) {
            $stepObj = new $className($this->dependencyInjector);
            $content = $stepObj->getContent();
        }

        return $content;
    }

    public function previousStepContent()
    {
        $step = $this->getStep() - 1;
        $this->setStep($step);

        return $this->getStepContent();
    }

    public function nextStepContent()
    {
        if ($this->getStep() === '6Vault') {
            $step = 7;
        } else {
            $step = $this->getStep() + 1;
        }
        $this->setStep($step);

        return $this->getStepContent();
    }

    public function vaultStepContent()
    {
        $this->setStep('6Vault');

        return $this->getStepContent();
    }
}
