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
 */

declare(strict_types=1);

if (!isset($centreon)) {
    exit();
}

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Core\Common\Domain\Exception\CollectionException;
use Core\Common\Domain\Exception\ValueObjectException;
use Core\Common\Domain\Exception\RepositoryException;

// If user isn’t admin, check ACL
if (!$oreon->user->admin) {
    if ($hc_id
        && $hcString !== "''"
        && ! str_contains($hcString, "'$hc_id'")
    ) {
        $msg = new CentreonMsg();
        $msg->setImage("./img/icons/warning.png");
        $msg->setTextStyle("bold");
        $msg->setText(_('You are not allowed to access this host category'));
        return;
    }
}

// Load initial values if editing or viewing
$initialValues = [];
$hc = [];
if (in_array($o, ['c','w'], true) && $hc_id) {
    try {
        $queryBuilder = $pearDB->createQueryBuilder();
        $query = $queryBuilder->select('*')
            ->from('hostcategories')
            ->where('hc_id = :hc_id')
            ->getQuery();

        $hc = $pearDB->fetchAssociative(
            $query,
            QueryParameters::create([
                QueryParameter::int('hc_id', (int) $hc_id)
            ])
        ) ?: [];
        // map old field names for the form
        $hc['hc_severity_level'] = $hc['level'] ?? '';
        $hc['hc_severity_icon'] = $hc['icon_id'] ?? '';
    } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error retrieving host category',
            ['hcId' => $hc_id],
            $exception
        );
        $msg = new CentreonMsg();
        $msg->setImage("./img/icons/warning.png");
        $msg->setTextStyle("bold");
        $msg->setText("Unable to load host category : hcId = $hc_id");
    }
}

// Fetch image lists
$extImg = return_image_list(1);
$extImgStatusmap = return_image_list(2);

/*
 * Define Templatse
 */
$attrsText = ['size' => '30'];
$attrsTextLong = ['size' => '50'];
$attrsAdvSelect = ['style' => 'width: 220px; height: 220px;'];
$attrsTextarea = ['rows' => '4', 'cols' => '60'];
$eTemplate = '<table><tr><td><div class="ams">{label_2}</div>{unselected}</td><td align="center">{add}<br /><br />'
    . '<br />{remove}</td><td><div class="ams">{label_3}</div>{selected}</td></tr></table>';
$hostRoute = './include/common/webServices/rest/internal.php?object=centreon_configuration_host&action=list';
$attrHosts = array(
    'datasourceOrigin' => 'ajax',
    'availableDatasetRoute' => $hostRoute,
    'multiple' => true,
    'linkedObject' => 'centreonHost'
);
$hostTplRoute = './include/common/webServices/rest/internal.php?object=centreon_configuration_hosttemplate&action=list';
$attrHosttemplates = array(
    'datasourceOrigin' => 'ajax',
    'availableDatasetRoute' => $hostTplRoute,
    'multiple' => true,
    'linkedObject' => 'centreonHosttemplates'
);

/*
 * Create formulary
 */
$form = new HTML_QuickFormCustom('Form', 'post', "?p=$p");

switch ($o) {
    case 'a':
        $form->addElement('header', 'title',_('Add a host category'));
        break;
    case 'c':
        $form->addElement('header', 'title',_('Modify a  host category'));
        break;
    case 'w':
        $form->addElement('header', 'title',_('View a  host category'));
        break;
}

/*
 * Catrgorie basic information
 */
$form->addElement('header', 'information', _('General Information'));
$form->addElement('text', 'hc_name', _('Name'), $attrsText);
$form->addElement('text', 'hc_alias', _('Alias'), $attrsText);

/*
 * Severity
 */
$form->addElement('header', 'relation', _('Relation'));
$hctype = $form->addElement('checkbox', 'hc_type', _('Severity type'), null, ['id' => 'hc_type']);
if (isset($hc_id) && isset($hc['level']) && $hc['level'] != "") {
    $hctype->setValue('1');
}
$form->addElement('text', 'hc_severity_level', _('Level'), ['size' => '10']);
$form->addElement(
    'select', 'hc_severity_icon', _('Icon'),
    return_image_list(1),
    ['id' => 'icon_id', 'onChange' => "showLogo('icon_id_ctn', this.value)"]
);

// Linked hosts / templates
$defHosts = './include/common/webServices/rest/internal.php?object=centreon_configuration_host'
          . "&action=defaultValues&target=hostcategories&field=hc_hosts&id=$hc_id";
$form->addElement('select2', 'hc_hosts', _('Linked Hosts'), [], array_merge($attrHosts,['defaultDatasetRoute'=>$defHosts]));

$defTpls = './include/common/webServices/rest/internal.php?object=centreon_configuration_hosttemplate'
         . "&action=defaultValues&target=hostcategories&field=hc_hostsTemplate&id={$hc_id}";
$ams1 = $form->addElement('select2', 'hc_hostsTemplate', _('Linked Host Template'), [], array_merge($attrHosttemplates,['defaultDatasetRoute'=>$defTpls]));
if (! $oreon->user->admin) {
    $ams1->setPersistantFreeze(true);
    $ams1->freeze();
}

/*
 * Further informations
 */
$form->addElement('header', 'furtherInfos', _('Additional Information'));
$form->addElement('textarea', 'hc_comment', _('Comments'), $attrsTextarea);
$hcActivation[] = $form->createElement('radio', 'hc_activate', null, _('Enabled'), '1');
$hcActivation[] = $form->createElement('radio', 'hc_activate', null, _('Disabled'), '0');
$form->addGroup($hcActivation, 'hc_activate', _('Status'), '&nbsp;');
$form->setDefaults(['hc_activate' => '1']);

// Hidden fields
$form->addElement('hidden', 'hc_id');
$redirect = $form->addElement('hidden', 'o');
$redirect->setValue($o);

$init = $form->addElement('hidden', 'initialValues');
$init->setValue(serialize($initialValues));

/*
 * Form Rules
 */
function myReplace() {
    global $form;
    $name = $form->getSubmitValues()['hc_name'] ?? '';
    return str_replace(' ', '_',$name);
}
$form->applyFilter('__ALL__', 'myTrim');
$form->applyFilter('hc_name', 'myReplace');
$form->addRule('hc_name', _('Compulsory Name'), 'required');
$form->addRule('hc_alias', _('Compulsory Alias'), 'required');

$form->registerRule('exist', 'callback', 'testHostCategorieExistence');
$form->addRule('hc_name', _('Name is already in use'), 'exist');
$form->setRequiredNote("<font color='red;'>*</font>" . _('Required fields'));

$form->addRule('hc_severity_level', _('Must be a number'), 'numeric');

$form->registerRule('shouldNotBeEqTo0', 'callback', 'shouldNotBeEqTo0');
$form->addRule('hc_severity_level', _("Can't be equal to 0"), 'shouldNotBeEqTo0');

$form->addFormRule('checkSeverity');

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);

$tpl->assign(
    'helpattr',
    'TITLE, "' . _('Help') . '", CLOSEBTN, true, FIX, [this, 0, 5], BGCOLOR, "#ffff99", '
  . 'BORDERCOLOR, "orange", TITLEFONTCOLOR, "black", TITLEBGCOLOR, "orange", '
  . 'CLOSEBTNCOLORS, ["","black", "white", "red"], WIDTH, -300, SHADOW, true, TEXTALIGN, "justify"'
);
$helptext = '';
include_once("help.php");
foreach ($help as $key => $text) {
    $helptext .= "<span style=\"display:none\" id=\"help:$key\">$text</span>\n";
}
$tpl->assign('helptext', $helptext);

if ($o === 'w') {
    /*
     * Just watch a HostCategorie information
     */
    if ($centreon->user->access->page($p) != 2) {
        $form->addElement(
            'button', 'change', _('Modify'),
            ['onClick' => "window.location.href='?p=$p&o=c&hc_id=$hc_id'"]
        );
    }
    $form->setDefaults($hc);
    $form->freeze();
} elseif ($o === 'c') {
    /*
     * Modify a HostCategorie information
     */
    $form->addElement('submit', 'submitC', _('Save'), ['class' => 'btc bt_success']);
    $form->addElement('reset', 'reset', _('Reset'), ['class' => 'btc bt_default']);
    $form->setDefaults($hc);
} elseif ($o === 'a') {
    $form->addElement('submit', 'submitA', _('Save'), ['class' => 'btc bt_success']);
    $form->addElement('reset', 'reset', _('Reset'), ['class' => 'btc bt_default']);
}

$tpl->assign('p', $p);

$valid = false;
if ($form->validate()) {
    $hcObj = $form->getElement('hc_id');
    if ($form->getSubmitValue('submitA')) {
        try {
            // Insert and capture new ID
            $newId = insertHostCategoriesInDB();
            $hcObj->setValue($newId);
            $valid = true;
        } catch (RepositoryException $exception) {
            CentreonLog::create()->error(
                CentreonLog::TYPE_SQL,
                "Error while inserting host category: " . $exception->getMessage(),
                exception: $exception
            );
            $msg = new CentreonMsg();
            $msg->setImage("./img/icons/warning.png");
            $msg->setTextStyle("bold");
            $msg->setText('Error while inserting host category');
        }
    }
    elseif ($form->getSubmitValue('submitC')) {
        try {
            // Update existing record
            updateHostCategoriesInDB((int) $hcObj->getValue());
            $valid = true;
        } catch (RepositoryException $exception) {
            CentreonLog::create()->error(
                CentreonLog::TYPE_SQL,
                "Error while updating host category: " . $exception->getMessage(),
                exception: $exception
            );
            $msg = new CentreonMsg();
            $msg->setImage("./img/icons/warning.png");
            $msg->setTextStyle("bold");
            $msg->setText('Error while updating host category');
        }
    }
}

if ($valid) {
    require_once($path . "listHostCategories.php");
} else {
    /*
     * Apply a template definition
     */
    $renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl, true);
    $renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
    $renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
    $form->accept($renderer);
    $tpl->assign('form', $renderer->toArray());
    $tpl->assign('o', $o);
    $tpl->assign('topdoc', _('Documentation'));
    $tpl->display('formHostCategories.ihtml');
}
