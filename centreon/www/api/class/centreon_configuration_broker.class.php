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

require_once __DIR__ . '/centreon_configuration_objects.class.php';

/**
 * Class
 *
 * @class CentreonConfigurationBroker
 */
class CentreonConfigurationBroker extends CentreonConfigurationObjects
{
    /**
     * @throws Exception
     * @return false|string
     */
    public function getBlock()
    {
        if (! isset($this->arguments['page'])
            || ! isset($this->arguments['position'])
            || ! isset($this->arguments['blockId'])
            || ! isset($this->arguments['tag'])
        ) {
            throw new Exception('Missing argument');
        }

        $page = filter_var((int) $this->arguments['page'], FILTER_VALIDATE_INT);
        $position = filter_var((int) $this->arguments['position'], FILTER_VALIDATE_INT);
        $blockId = HtmlAnalyzer::sanitizeAndRemoveTags((string) $this->arguments['blockId']);
        $tag = HtmlAnalyzer::sanitizeAndRemoveTags((string) $this->arguments['tag']);
        if (empty($tag) || empty($blockId) || $page === false || $position === false) {
            throw new InvalidArgumentException('Invalid Parameters');
        }

        $cbObj = new CentreonConfigCentreonBroker($this->pearDB);

        $form = $cbObj->quickFormById($blockId, $page, $position, 'new_' . rand(100, 1000));

        $helps = [];
        [$tagId, $typeId] = explode('_', $blockId);
        $typeName = $cbObj->getTypeName($typeId);
        $fields = $cbObj->getBlockInfos($typeId);
        $helps[] = ['name' => $tag . '[' . $position . '][name]', 'desc' => _('The name of block configuration')];
        $helps[] = ['name' => $tag . '[' . $position . '][type]', 'desc' => _('The type of block configuration')];
        $cbObj->nbSubGroup = 1;
        textdomain('help');
        foreach ($fields as $field) {
            $fieldname = '';
            if ($field['group'] !== '') {
                $fieldname .= $cbObj->getParentGroups($field['group']);
            }
            $fieldname .= $field['fieldname'];
            $helps[] = ['name' => $tag . '[' . $position . '][' . $fieldname . ']', 'desc' => _($field['description'])];
        }
        textdomain('messages');

        // Smarty template Init
        $libDir = __DIR__ . '/../../../GPL_LIB';
        $tpl = new SmartyBC();
        $tpl->setTemplateDir(_CENTREON_PATH_ . '/www/include/configuration/configCentreonBroker/');
        $tpl->setCompileDir($libDir . '/SmartyCache/compile');
        $tpl->setConfigDir($libDir . '/SmartyCache/config');
        $tpl->setCacheDir($libDir . '/SmartyCache/cache');
        $tpl->addPluginsDir($libDir . '/smarty-plugins');
        $tpl->loadPlugin('smarty_function_eval');
        $tpl->setForceCompile(true);
        $tpl->setAutoLiteral(false);

        // Apply a template definition
        $renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
        $renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
        $renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
        $form->accept($renderer);
        $tpl->assign('formBlock', $renderer->toArray());
        $tpl->assign('typeName', $typeName);
        $tpl->assign('tagBlock', $tag);
        $tpl->assign('posBlock', $position);
        $tpl->assign('helps', $helps);

        return $tpl->fetch('blockConfig.ihtml');
    }
}
