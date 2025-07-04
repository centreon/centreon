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

require_once __DIR__ . '/webService.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonCustomView.class.php';

/**
 * Class
 *
 * @class CentreonHomeCustomview
 */
class CentreonHomeCustomview extends CentreonWebService
{
    /**
     * CentreonHomeCustomview constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @throws PDOException
     * @return array
     */
    public function getListSharedViews()
    {
        global $centreon;
        $views = [];
        $q = [];
        if (isset($this->arguments['q']) && $this->arguments['q'] != '') {
            $q[] = '%' . $this->arguments['q'] . '%';
        }

        $query = 'SELECT custom_view_id, name FROM ('
            . 'SELECT cv.custom_view_id, cv.name FROM custom_views cv '
            . 'INNER JOIN custom_view_user_relation cvur ON cv.custom_view_id = cvur.custom_view_id '
            . 'WHERE (cvur.user_id = ' . $centreon->user->user_id . ' '
            . 'OR cvur.usergroup_id IN ( '
            . 'SELECT contactgroup_cg_id '
            . 'FROM contactgroup_contact_relation '
            . 'WHERE contact_contact_id = ' . $centreon->user->user_id . ' '
            . ') '
            . ') '
            . 'UNION '
            . 'SELECT cv2.custom_view_id, cv2.name FROM custom_views cv2 '
            . 'WHERE cv2.public = 1 ) as d '
            . 'WHERE d.custom_view_id NOT IN ('
            . 'SELECT cvur2.custom_view_id FROM custom_view_user_relation cvur2 '
            . 'WHERE cvur2.user_id = ' . $centreon->user->user_id . ' '
            . 'AND cvur2.is_consumed = 1) '
            . ($q !== [] ? 'AND d.name like ? ' : '')
            . 'ORDER BY name';

        $stmt = $this->pearDB->prepare($query);
        $stmt->execute($q);

        while ($row = $stmt->fetch()) {
            $views[] = ['id' => $row['custom_view_id'], 'text' => $row['name']];
        }

        return ['items' => $views, 'total' => count($views)];
    }

    /**
     * @throws RestBadRequestException
     * @return array
     */
    public function getLinkedUsers()
    {
        // Check for select2 'q' argument
        if (isset($this->arguments['q'])) {
            if (! is_numeric($this->arguments['q'])) {
                throw new RestBadRequestException('Error, custom view id must be numerical');
            }
            $customViewId = $this->arguments['q'];
        } else {
            $customViewId = 0;
        }

        global $centreon;
        $viewObj = new CentreonCustomView($centreon, $this->pearDB);

        return $viewObj->getUsersFromViewId($customViewId);
    }

    /**
     * @throws RestBadRequestException
     * @return array
     */
    public function getLinkedUsergroups()
    {
        // Check for select2 'q' argument
        if (isset($this->arguments['q'])) {
            if (! is_numeric($this->arguments['q'])) {
                throw new RestBadRequestException('Error, custom view id must be numerical');
            }
            $customViewId = $this->arguments['q'];
        } else {
            $customViewId = 0;
        }

        global $centreon;
        $viewObj = new CentreonCustomView($centreon, $this->pearDB);

        return $viewObj->getUsergroupsFromViewId($customViewId);
    }

    /**
     * Get the list of views
     *
     * @throws Exception
     * @return array
     */
    public function getListViews()
    {
        global $centreon;
        $viewObj = new CentreonCustomView($centreon, $this->pearDB);

        $tabs = [];
        $tabsDb = $viewObj->getCustomViews();
        foreach ($tabsDb as $key => $tab) {
            $tabs[] = ['default' => false, 'name' => $tab['name'], 'custom_view_id' => $tab['custom_view_id'], 'public' => $tab['public'], 'nbCols' => $tab['layout']];
        }

        return ['current' => $viewObj->getCurrentView(), 'tabs' => $tabs];
    }

    /**
     * Get the list of preferences
     * @throws Exception
     * @return false|string
     */
    public function getPreferences()
    {
        if (
            filter_var(($widgetId = $this->arguments['widgetId'] ?? false), FILTER_VALIDATE_INT) === false
            || filter_var(($viewId = $this->arguments['viewId'] ?? false), FILTER_VALIDATE_INT) === false
        ) {
            throw new InvalidArgumentException('Bad argument format');
        }

        require_once _CENTREON_PATH_ . 'www/class/centreonWidget.class.php';
        require_once _CENTREON_PATH_ . 'www/class/centreonWidget/Params/Boolean.class.php';
        require_once _CENTREON_PATH_ . 'www/class/centreonWidget/Params/Hidden.class.php';
        require_once _CENTREON_PATH_ . 'www/class/centreonWidget/Params/List.class.php';
        require_once _CENTREON_PATH_ . 'www/class/centreonWidget/Params/Password.class.php';
        require_once _CENTREON_PATH_ . 'www/class/centreonWidget/Params/Range.class.php';
        require_once _CENTREON_PATH_ . 'www/class/centreonWidget/Params/Text.class.php';
        require_once _CENTREON_PATH_ . 'www/class/centreonWidget/Params/Compare.class.php';
        require_once _CENTREON_PATH_ . 'www/class/centreonWidget/Params/Sort.class.php';
        require_once _CENTREON_PATH_ . 'www/class/centreonWidget/Params/Date.class.php';
        $smartyDir = __DIR__ . '/../../../vendor/smarty/smarty/';
        require_once $smartyDir . 'libs/Smarty.class.php';

        global $centreon;

        $action = 'setPreferences';

        $viewObj = new CentreonCustomView($centreon, $this->pearDB);
        $widgetObj = new CentreonWidget($centreon, $this->pearDB);
        $title = '';
        $defaultTab = [];

        $widgetTitle = $widgetObj->getWidgetTitle($widgetId);
        $title = $widgetTitle != '' ? sprintf(_('Widget Preferences for %s'), $widgetTitle) : _('Widget Preferences');

        $info = $widgetObj->getWidgetDirectory($widgetObj->getWidgetType($widgetId));
        $title .= ' [' . $info . ']';

        $defaultTab['custom_view_id'] = $viewId;
        $defaultTab['widget_id'] = $widgetId;
        $defaultTab['action'] = $action;
        $url = $widgetObj->getUrl($widgetId);

        // Smarty template Init
        $libDir = __DIR__ . '/../../../GPL_LIB';
        $tpl = new SmartyBC();
        $tpl->setTemplateDir(_CENTREON_PATH_ . '/www/include/home/customViews/');
        $tpl->setCompileDir($libDir . '/SmartyCache/compile');
        $tpl->setConfigDir($libDir . '/SmartyCache/config');
        $tpl->setCacheDir($libDir . '/SmartyCache/cache');
        $tpl->addPluginsDir($libDir . '/smarty-plugins');
        $tpl->loadPlugin('smarty_function_eval');
        $tpl->setForceCompile(true);
        $tpl->setAutoLiteral(false);

        $form = new HTML_QuickFormCustom('Form', 'post', '?p=103');
        $form->addElement('header', 'title', $title);
        $form->addElement('header', 'information', _('General Information'));

        // Prepare list of installed modules and have widget connectors
        $loadConnectorPaths = [];
        // Add core path
        $loadConnectorPaths[] = _CENTREON_PATH_ . 'www/class/centreonWidget/Params/Connector';
        $query = 'SELECT name FROM modules_informations ORDER BY name';
        $res = $this->pearDB->query($query);
        while ($module = $res->fetchRow()) {
            $dirPath = _CENTREON_PATH_ . 'www/modules/' . $module['name'] . '/widgets/Params/Connector';
            if (is_dir($dirPath)) {
                $loadConnectorPaths[] = $dirPath;
            }
        }

        try {
            $permission = $viewObj->checkPermission($viewId);
            $params = $widgetObj->getParamsFromWidgetId($widgetId, $permission);
            foreach ($params as $paramId => $param) {
                if ($param['is_connector']) {
                    $paramClassFound = false;
                    foreach ($loadConnectorPaths as $path) {
                        $filename = $path . '/' . ucfirst($param['ft_typename'] . '.class.php');
                        if (is_file($filename)) {
                            require_once $filename;
                            $paramClassFound = true;
                            break;
                        }
                    }
                    if (false === $paramClassFound) {
                        throw new Exception('No connector found for ' . $param['ft_typename']);
                    }
                    $className = 'CentreonWidgetParamsConnector' . ucfirst($param['ft_typename']);
                } else {
                    $className = 'CentreonWidgetParams' . ucfirst($param['ft_typename']);
                }
                if (class_exists($className)) {
                    $currentParam = call_user_func(
                        [$className, 'factory'],
                        $this->pearDB,
                        $form,
                        $className,
                        $centreon->user->user_id
                    );
                    $param['custom_view_id'] = $viewId;
                    $param['widget_id'] = $widgetId;
                    $currentParam->init($param);
                    $currentParam->setValue($param);
                    $params[$paramId]['trigger'] = $currentParam->getTrigger();
                    $element = $currentParam->getElement();
                } else {
                    throw new Exception('No class name found');
                }
            }
        } catch (Exception $e) {
            echo $e->getMessage() . '<br/>';
        }

        $tpl->assign('params', $params);

        /**
         * Submit button
         */
        $form->addElement(
            'button',
            'submit',
            _('Apply'),
            ['class' => 'btc bt_success', 'onClick' => 'submitData();']
        );
        $form->addElement('reset', 'reset', _('Reset'), ['class' => 'btc bt_default']);
        $form->addElement('hidden', 'custom_view_id');
        $form->addElement('hidden', 'widget_id');
        $form->addElement('hidden', 'action');
        $form->setDefaults($defaultTab);

        // Apply a template definition
        $renderer = new HTML_QuickForm_Renderer_ArraySmarty($template, true);
        $renderer->setRequiredTemplate('{$label}&nbsp;<i class="red">*</i>');
        $renderer->setErrorTemplate('<i class="red">{$error}</i><br />{$html}');
        $form->accept($renderer);
        $tpl->assign('form', $renderer->toArray());
        $tpl->assign('viewId', $viewId);
        $tpl->assign('widgetId', $widgetId);
        $tpl->assign('url', $url);

        return $tpl->fetch('widgetParam.html');
    }

    /**
     * Get preferences by widget id
     *
     * @throws Exception When missing argument
     * @return array The widget preferences
     */
    public function getPreferencesByWidgetId()
    {
        global $centreon;

        if (! isset($this->arguments['widgetId'])) {
            throw new Exception('Missing argument : widgetId');
        }
        $widgetId = $this->arguments['widgetId'];
        $widgetObj = new CentreonWidget($centreon, $this->pearDB);

        return $widgetObj->getWidgetPreferences($widgetId);
    }

    /**
     * Authorize to access to the action
     *
     * @param string $action The action name
     * @param CentreonUser $user The current user
     * @param bool $isInternal If the api is call in internal
     * @return bool If the user has access to the action
     */
    public function authorize($action, $user, $isInternal = false)
    {
        return (bool) (
            parent::authorize($action, $user, $isInternal)
            || ($user && $user->hasAccessRestApiConfiguration())
        );
    }
}
