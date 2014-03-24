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

namespace Controllers\Configuration;

use \Centreon\Core\Form;

class ServicegroupController extends \Controllers\ObjectAbstract
{
    protected $objectDisplayName = 'Servicegroup';
    protected $objectName = 'servicegroup';
    protected $objectBaseUrl = '/configuration/servicegroup';
    protected $objectClass = '\Models\Configuration\Servicegroup';

    /**
     * List servicegroups
     *
     * @method get
     * @route /configuration/servicegroup
     */
    public function listAction()
    {
        parent::listAction();
    }

    /**
     * List service groups 
     *
     * @method get
     * @route /configuration/servicegroup/formlist
     */
    public function formListAction()
    {
        parent::formListAction();
    }

    /**
     * 
     * @method get
     * @route /configuration/servicegroup/list
     */
    public function datatableAction()
    {
        parent::datatableAction();
    }
    
    /**
     * Create a new servicegroup
     *
     * @method post
     * @route /configuration/servicegroup/create
     */
    public function createAction()
    {
        
    }

    /**
     * Update a servicegroup
     *
     *
     * @method put
     * @route /configuration/servicegroup/update
     */
    public function updateAction()
    {
        
    }
    
    /**
     * Add a servicegroup
     *
     *
     * @method get
     * @route /configuration/servicegroup/add
     */
    public function addAction()
    {
        parent::addAction();
    }
    
    /**
     * Update a servicegroup
     *
     *
     * @method get
     * @route /configuration/servicegroup/[i:id]
     */
    public function editAction()
    {
        // Init template
        $di = \Centreon\Core\Di::getDefault();
        $tpl = $di->get('template');
        
        $form = new Form('servicegroupForm');
        $form->addText('name', _('Service Group Name'));
        $form->addText('description', _('Service Description'));
        $radios['list'] = array(
            array(
              'name' => 'Enabled',
              'label' => 'Enabled',
              'value' => '1'
            ),
            array(
                'name' => 'Disabled',
                'label' => 'Disabled',
                'value' => '0'
            )
        );
        $form->addRadio('servicegroup_status', _("Status"), 'servicegroup_type', '&nbsp;', $radios);
        $form->addTextarea('comments', _('Comments'));
        $form->add('save_form', 'submit', _("Save"), array("onClick" => "validForm();"));
        $tpl->assign('form', $form->toSmarty());
        
        // Display page
        $tpl->display('configuration/servicegroup/edit.tpl');
    }


    /**
     * Get the list of massive change fields
     *
     * @method get
     * @route /configuration/servicegroup/mc_fields
     */
    public function getMassiveChangeFieldsAction()
    {
        parent::getMassiveChangeFieldsAction();
    }

    /**
     * Get the html of attribute filed
     *
     * @method get
     * @route /configuration/servicegroup/mc_fields/[i:id]
     */
    public function getMcFieldAction()
    {
        parent::getMcFieldAction();
    }

    /**
     * Duplicate a hosts
     *
     * @method POST
     * @route /configuration/servicegroup/duplicate
     */
    public function duplicateAction()
    {
        parent::duplicateAction();
    }

    /**
     * Apply massive change
     *
     * @method POST
     * @route /configuration/servicegroup/massive_change
     */
    public function massiveChangeAction()
    {
        parent::massiveChangeAction();
    }

    /**
     * Delete action for servicegroup
     *
     * @method post
     * @route /configuration/servicegroup/delete
     */
    public function deleteAction()
    {
        parent::deleteAction();
    }
}
