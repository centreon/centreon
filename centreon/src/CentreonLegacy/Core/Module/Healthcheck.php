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

namespace CentreonLegacy\Core\Module;

use CentreonLegacy\ServiceProvider;
use DateTime;
use Psr\Container\ContainerInterface;

/**
 * Check module requirements and health.
 */
class Healthcheck
{
    /** @var string Path to the module */
    protected $modulePath;

    /** @var array|null Collect error messages after check */
    protected $messages;

    /** @var array|null Collect a custom action after check */
    protected $customAction;

    /** @var DateTime|null Collect date and time of a license expiration */
    protected $licenseExpiration;

    /**
     * Construct.
     *
     * @param ContainerInterface $services
     */
    public function __construct(ContainerInterface $services)
    {
        $this->modulePath = $services->get(ServiceProvider::CONFIGURATION)
            ->getModulePath();
    }

    /**
     * Check module requirements and health.
     *
     * @param string $module
     *
     * @throws Exception\HealthcheckNotFoundException
     * @throws Exception\HealthcheckCriticalException
     * @throws Exception\HealthcheckWarningException
     *
     * @return bool|null
     */
    public function check($module): ?bool
    {
        // reset messages stack
        $this->reset();

        if (! preg_match('/^(?!\.)/', $module)) {
            throw new Exception\HealthcheckNotFoundException("Incorrect module name {$module}");
        }
        if (! is_dir($this->modulePath . $module)) {
            throw new Exception\HealthcheckNotFoundException("Module did not exist {$this->modulePath} {$module}");
        }

        $checklistDir = $this->modulePath . $module . '/checklist/';
        $warning = false;
        $critical = false;

        if (file_exists($checklistDir . 'requirements.php')) {
            $message = [];
            $licenseExpiration = null;
            $customAction = null;

            $this->getRequirements($checklistDir, $message, $customAction, $warning, $critical, $licenseExpiration);

            // Necessary to implement the expiration date column in list modules page
            if (! empty($licenseExpiration)) {
                $this->licenseExpiration = new DateTime(date(DateTime::W3C, $licenseExpiration));
            }

            if (! $critical && ! $warning) { // critical: FALSE, warning: FALSE
                $this->setCustomAction($customAction);

                return true;
            }

            $this->setMessages($message);

            if (! $critical && $warning) { // critical: FALSE, warning: TRUE
                throw new Exception\HealthcheckWarningException();
            }

            // critical: TRUE
            throw new Exception\HealthcheckCriticalException();
        }

        throw new Exception\HealthcheckNotFoundException('The module\'s requirements did not exist');
    }

    /**
     * Made the check method compatible with moduleDependenciesValidator.
     *
     * @param string $module
     *
     * @return array|null
     */
    public function checkPrepareResponse($module): ?array
    {
        $result = null;

        try {
            $this->check($module);

            $result = [
                'status' => 'ok',
            ];

            if ($this->getCustomAction()) {
                $result = array_merge($result, $this->getCustomAction());
            }
        } catch (Exception\HealthcheckCriticalException $ex) {
            $result = [
                'status' => 'critical',
            ];

            if ($this->getMessages()) {
                $result = array_merge($result, [
                    'message' => $this->getMessages(),
                ]);
            }
        } catch (Exception\HealthcheckWarningException $ex) {
            $result = [
                'status' => 'warning',
            ];

            if ($this->getMessages()) {
                $result = array_merge($result, [
                    'message' => $this->getMessages(),
                ]);
            }
        } catch (Exception\HealthcheckNotFoundException $ex) {
            $result = [
                'status' => 'notfound',
            ];
        } catch (\Exception $ex) {
            $result = [
                'status' => 'critical',
                'message' => [
                    'ErrorMessage' => $ex->getMessage(),
                    'Solution' => '',
                ],
            ];
        }

        if ($this->getLicenseExpiration()) {
            $result['licenseExpiration'] = $this->getLicenseExpiration()->getTimestamp();
        }

        return $result;
    }

    /**
     * Reset collected data after check.
     */
    public function reset(): void
    {
        $this->messages = null;
        $this->customAction = null;
        $this->licenseExpiration = null;
    }

    public function getMessages(): ?array
    {
        return $this->messages;
    }

    public function getCustomAction(): ?array
    {
        return $this->customAction;
    }

    public function getLicenseExpiration(): ?DateTime
    {
        return $this->licenseExpiration;
    }

    /**
     * Load a file with requirements.
     *
     * @codeCoverageIgnore
     *
     * @param string $checklistDir
     * @param array $message
     * @param array $customAction
     * @param bool $warning
     * @param bool $critical
     * @param int $licenseExpiration
     */
    protected function getRequirements(
        $checklistDir,
        &$message,
        &$customAction,
        &$warning,
        &$critical,
        &$licenseExpiration
    ) {
        global $centreon_path;
        require_once $checklistDir . 'requirements.php';
    }

    protected function setMessages(array $messages)
    {
        foreach ($messages as $errorMessage) {
            $this->messages = [
                'ErrorMessage' => $errorMessage['ErrorMessage'],
                'Solution' => $errorMessage['Solution'],
            ];
        }
    }

    protected function setCustomAction(?array $customAction = null)
    {
        if ($customAction !== null) {
            $this->customAction = [
                'customAction' => $customAction['action'],
                'customActionName' => $customAction['name'],
            ];
        }
    }
}
