<?php
/*
 * Copyright 2005-2014 MERETHIS
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give MERETHIS
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of MERETHIS choice, provided that
 * MERETHIS also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

namespace Centreon\Core\Form;

/**
 * @author Lionel Assepo <lassepo@merethis.com>
 * @package Centreon
 * @subpackage Core
 */
class Generator
{
    /**
     *
     * @var type 
     */
    protected $formName = '';
    
    /**
     *
     * @var type 
     */
    protected $formRoute;
    
    /**
     *
     * @var type 
     */
    private $formRedirect;
    
    /**
     *
     * @var type 
     */
    private $formRedirectRoute;
    
    /**
     *
     * @var type 
     */
    protected $formComponents = array();
    
    /**
     *
     * @var type 
     */
    protected $formDefautls = array();
    
    /**
     *
     * @var type 
     */
    protected $formHandler;
    
    /**
     *
     * @var type 
     */
    private $firstSection;
    
    /**
     *
     * @var type 
     */
    protected $extraParams;


    /**
     * 
     * @param sring $formRoute
     * @param boolean $advanced
     * @param array $extraParams
     */
    public function __construct($formRoute, $advanced = 0, $extraParams = array())
    {
        $this->formRoute = $formRoute;
        $this->extraParams = $extraParams;
        $this->getFormFromDatabase($advanced);
    }
    
    /**
     * 
     * @param boolean $advanced
     */
    protected function getFormFromDatabase($advanced = 0)
    {
        // Initializing connection
        $di = \Centreon\Core\Di::getDefault();
        $dbconn = $di->get('db_centreon');
        
        $queryForm = "SELECT form_id, name, redirect, redirect_route FROM form WHERE route = '$this->formRoute'";
        $stmtForm = $dbconn->query($queryForm);
        $formInfo = $stmtForm->fetchAll();
        
        $formId = $formInfo[0]['form_id'];
        $this->formName = $formInfo[0]['name'];
        $this->formRedirect = $formInfo[0]['redirect'];
        $this->formRedirectRoute = $formInfo[0]['redirect_route'];
        
        $this->formHandler = new \Centreon\Core\Form($this->formName);
        
        $sectionQuery = 'SELECT section_id, name '
            . 'FROM form_section '
            . 'WHERE form_id='.$formId.' '
            . 'ORDER BY rank ASC';
        
        $sectionStmt = $dbconn->query($sectionQuery);
        $sectionList = $sectionStmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $firstSectionDetected = false;
        
        foreach($sectionList as $section) {
            if (!$firstSectionDetected) {
                $this->firstSection = $section['name'];
                $firstSectionDetected = true;
            }
            
            $blockQuery = 'SELECT block_id, name '
            . 'FROM form_block '
            . 'WHERE section_id='.$section['section_id'].' '
            . 'ORDER BY rank ASC';
            
            $blockStmt = $dbconn->query($blockQuery);
            $blockList = $blockStmt->fetchAll(\PDO::FETCH_ASSOC);
            $this->formComponents[$section['name']] = array();
            
            foreach($blockList as $block) {
                
                if ($advanced) {
                    $advancedRequest = "AND (advanced = '0' OR advanced = '1' )";
                } else {
                    $advancedRequest = "AND advanced = '0' ";
                }
                
                $fieldQuery = 'SELECT '
                    . 'name, label, default_value, attributes, type, help, mandatory, parent_field, child_actions, v.action as validator_action'
                    . 'FROM form_field f, form_block_field_relation bfr '
                    . 'LEFT JOIN field_validator_relation v on v.field_id = bfr.field_id '
                    . 'WHERE bfr.block_id='.$block['block_id'].' '
                    . 'AND bfr.field_id = f.field_id '
                    . $advancedRequest
                    . 'ORDER BY rank ASC';
                
                $this->formComponents[$section['name']][$block['name']] = array();
                $fieldStmt = $dbconn->query($fieldQuery);
                $fieldList = $fieldStmt->fetchAll(\PDO::FETCH_ASSOC);
                
                foreach ($fieldList as $field) {
                    $this->addFieldToForm($field);
                    $this->formComponents[$section['name']][$block['name']][] = $field;
                    $this->formDefaults[$field['name']] = $field['default_value'];
                }
                
                if (count($this->formComponents[$section['name']][$block['name']]) == 0) {
                    unset($this->formComponents[$section['name']][$block['name']]);
                }
            }
            if (count($this->formComponents[$section['name']]) == 0) {
                unset($this->formComponents[$section['name']]);
            }
        }
        $this->formHandler->addSubmit('save_form', _("Save"));
    }
    
    /**
     * 
     * @param array $field
     */
    protected function addFieldToForm($field)
    {
        switch ($field['type']) {
            default:
                $this->formHandler->addStatic($field, $this->extraParams);
                break;
            case 'textarea':
                $this->formHandler->addTextarea($field['name'], $field['label']);
                break;
            
            case 'radio':
                $values = json_decode($field['attributes']);
                $radioValues = array();
                foreach ($values as $label=>$value) {
                    $radioValues['list'][] = array(
                        'name' => $label,
                        'label' => $label,
                        'value' => $value
                    );
                }
                $this->formHandler->addRadio(
                    $field['name'],
                    $field['label'],
                    $field['name'],
                    '&nbsp;',
                    $radioValues
                );
                break;
                
            case 'checkbox':
                $values = json_decode($field['attributes']);
                if (is_array($values) || is_object($values)) {
                    $checkboxValues = array();
                    foreach ($values as $label=>$value) {
                        $checkboxValues['list'][] = array(
                            'name' => $label,
                            'label' => $label,
                            'value' => $value
                        );
                    }
                    $this->formHandler->addCheckBox(
                        $field['name'],
                        $field['label'],
                        '&nbsp;',
                        $checkboxValues
                    );
                } else { 
                    $this->formHandler->addCheckbox($field['name'], $field['label']);
                }
                break;
        }
    }
    
    /**
     * 
     * @return string
     */
    public function generate()
    {
        $di = \Centreon\Core\Di::getDefault();
        $tpl = $di->get('template');
        $finalHtml = $this->generateHtml();
        $tpl->assign('formRedirect', $this->formRedirect);
        $tpl->assign('formRedirectRoute', $this->formRedirectRoute);
        $tpl->assign('customValuesGetter', $this->formHandler->getCustomValidator());
        return $finalHtml;
    }
    
    /**
     * 
     * @return string
     */
    protected function generateHtml()
    {
        $this->formHandler->setDefaults($this->formDefautls);
        $formElements = $this->formHandler->toSmarty();
        
        $htmlRendering = '<div class="row">';
        
        $htmlRendering .= '<div class="bs-callout bs-callout-success" id="formSuccess" style="display: none;">The object has been successfully updated</div>';
        $htmlRendering .= '<div class="bs-callout bs-callout-danger" id="formError" style="display: none;">An error occured</div>';
        
        $htmlRendering .= '<form class="form-horizontal" role="form" '.$formElements['attributes'].'>';
        
        $formRendering = '';
        
        $tabRendering = '<ul class="nav nav-tabs" id="formHeader">';
        
        foreach ($this->formComponents as $sectionLabel=>$sectionComponents) {
            $tabRendering .= '<li><a href="#'.str_replace(' ', '', $sectionLabel).'" data-toggle="tab">'.$sectionLabel.'</a></li>';
        }
        $formRendering .= '</ul>';
        
        $formRendering .= '<div class="tab-content">';
        foreach ($this->formComponents as $sectionLabel=>$sectionComponents) {
            $formRendering .= '<div class="tab-pane" id="'.str_replace(' ', '', $sectionLabel).'">';
            foreach ($sectionComponents as $blockLabel=>$blockComponents) {
                $formRendering .= '<div class="panel panel-default">';
                $formRendering .= '<div class="panel-heading">';
                $formRendering .= '<h3 class="panel-title">'.$blockLabel.'</h3>';
                $formRendering .= '</div>';
                $formRendering .= '<div class="panel-body">';
                foreach($blockComponents as $component) {
                    if (isset($formElements[$component['name']]['html'])) {
                        $formRendering .= $formElements[$component['name']]['html'];
                    }
                }
                $formRendering .= '</div>';
                $formRendering .= '</div>';
            }
            $formRendering .= '</div>';
        }
        $formRendering .= '</div>';
        
        $formRendering .= '<div>'.$formElements['save_form']['html'].'</div>';
        
        $formRendering .= $formElements['hidden'];
        $htmlRendering .= $tabRendering.$formRendering.'</form></div>';
        
        return $htmlRendering;
    }

    /**
     * 
     * @return string
     */
    public function getName()
    {
        return $this->formName;
    }
    
    /**
     * 
     * @return string
     */
    public function getRedirect()
    {
        return $this->formRedirect;
    }
    
    /**
     * 
     * @return string
     */
    public function getRedirectRoute()
    {
        return $this->formRedirectRoute;
    }

    /**
     * 
     * @param string $name
     * @param string $value
     */
    public function addHiddenComponent($name, $value)
    {
        $this->formHandler->addHidden($name, $value);
    }
    
    /**
     * 
     * @param array $defaultValues
     */
    public function setDefaultValues($defaultValues)
    {
        $this->formHandler->setDefaults($defaultValues);
    }
}
