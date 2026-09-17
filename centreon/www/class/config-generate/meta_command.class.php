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

/**
 * Class
 *
 * @class MetaCommand
 */
class MetaCommand extends AbstractObject
{
    /** @var string */
    protected $generate_filename = 'meta_commands.cfg';

    /** @var string */
    protected string $object_name = 'command';

    /** @var string[] */
    protected $attributes_write = ['command_name', 'command_line'];

    /**
     * @throws Exception
     * @return int|void
     */
    public function generateObjects()
    {
        if ($this->checkGenerate(0)) {
            return 0;
        }

        $object = [];
        $object['command_name'] = 'check_meta';
        $object['command_line'] = '$CENTREONPLUGINS$/centreon_centreon_central.pl '
            . '--plugin=apps::centreon::local::plugin --mode=metaservice --centreon-config=/etc/centreon/conf.pm '
            . '--meta-id $ARG1$';
        $this->generateObjectInFile($object, 0);

        $object['command_name'] = 'check_meta_host_alive';
        $object['command_line'] = '$CENTREONPLUGINS$/centreon_centreon_central.pl '
            . '--plugin=apps::centreon::local::plugin --mode=dummy --status=\'0\' --output=\'This is a dummy check\'';
        $this->generateObjectInFile($object, 0);
    }
}
