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

require_once _CENTREON_PATH_ . '/bootstrap.php';
require_once realpath(__DIR__ . '/../../../config/centreon.config.php');
require_once _CENTREON_PATH_ . '/www/class/centreonDB.class.php';
require_once __DIR__ . '/../../include/common/vault-functions.php';

use App\Kernel;
use Core\Common\Application\Repository\ReadVaultRepositoryInterface;
use Core\Security\Vault\Application\Repository\ReadVaultConfigurationRepositoryInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * Class
 *
 * @class Wiki
 */
class Wiki
{
    /** @var CentreonDB */
    private $db;

    /** @var array */
    private $config = null;

    /**
     * Wiki constructor
     *
     * @throws LogicException
     * @throws PDOException
     * @throws Throwable
     * @throws ServiceCircularReferenceException
     * @throws ServiceNotFoundException
     */
    public function __construct()
    {
        $this->db = new CentreonDB();
        $this->config = $this->getWikiConfig();
    }

    /**
     * @throws LogicException
     * @throws PDOException
     * @throws Throwable
     * @throws ServiceCircularReferenceException
     * @throws ServiceNotFoundException
     * @return array|mixed
     */
    public function getWikiConfig()
    {
        if (! is_null($this->config)) {
            return $this->config;
        }

        $options = [];

        $res = $this->db->query(
            "SELECT * FROM `options` WHERE options.key LIKE 'kb_%'"
        );
        while ($opt = $res->fetch()) {
            $options[$opt['key']] = html_entity_decode($opt['value'], ENT_QUOTES, 'UTF-8');
        }
        $res->closeCursor();

        if (! isset($options['kb_wiki_url']) || $options['kb_wiki_url'] == '') {
            throw new Exception(
                'Wiki is not configured. '
                . 'You can disable cron in /etc/cron.d/centreon for wiki synchronization.'
            );
        }

        if (! preg_match('#^http://|https://#', $options['kb_wiki_url'])) {
            $options['kb_wiki_url'] = 'http://' . $options['kb_wiki_url'];
        }

        if (isset($options['kb_wiki_password']) && str_starts_with($options['kb_wiki_password'], 'secret::')) {
            $kernel = Kernel::createForWeb();
            $readVaultConfigurationRepository = $kernel->getContainer()->get(
                ReadVaultConfigurationRepositoryInterface::class
            );
            $vaultConfiguration = $readVaultConfigurationRepository->find();
            if ($vaultConfiguration !== null) {
                /** @var ReadVaultRepositoryInterface $readVaultRepository */
                $readVaultRepository = $kernel->getContainer()->get(ReadVaultRepositoryInterface::class);
                $options['kb_wiki_password'] = findKnowledgeBasePasswordFromVault(
                    $readVaultRepository,
                    $options['kb_wiki_password']
                );
            }
        }

        return $options;
    }
}
