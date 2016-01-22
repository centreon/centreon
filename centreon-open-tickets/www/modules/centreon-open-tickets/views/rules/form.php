<?php
/*
 * CENTREON
 *
 * Source Copyright 2005-2015 CENTREON
 *
 * Unauthorized reproduction, copy and distribution
 * are not allowed.
 *
 * For more information : contact@centreon.com
 *
*/

$path = "./modules/centreon-open-tickets/views/rules/";
$tpl = new Smarty();
$tpl = initSmartyTpl($path, $tpl);

$required_field = '&nbsp;<font color="red" size="1">*</font>';

$tpl->assign('host', array('label' => _("Hosts")));
$tpl->assign('rule', array('label' => _("Rules")));

$tpl->assign("img_wrench", "./modules/centreon-open-tickets/images/wrench.png");
$tpl->assign("img_info", "./modules/centreon-open-tickets/images/information.png");

$tpl->assign("sort1", _("General"));
$tpl->assign("sort2", _("Advanced"));

$tpl->assign("header", array("title" => _("Rules"), "general" => _("General information")));

$result_rule = $rule->getAliasAndProviderId($ruleId);

$tpl->assign('rule_id', $ruleId);

$rule_alias_html = '<input size="30" name="rule_alias" type="text" value="' . (isset($result_rule['alias']) ? $result_rule['alias'] : '') . '" />';
$provider_html = '<select id="provider_id" name="provider_id"><option value=""></option>';
foreach ($register_providers as $name => $value) {
    $selected = '';
    if (isset($result_rule['provider_id']) && $result_rule['provider_id'] == $value) {
        $selected = ' selected ';
    }
    $provider_html .= '<option value="' . $value . '"$selected>' . $name . '</option>';
}
$provider_html .= '</select>';

$array_rule_form = array(
    'rule_alias' => array('label' => _("Rule name") . $required_field, 'html' => $rule_alias_html),
    'rule_provider' => array('label' => _("Provider") . $required_field, 'html' => $provider_html)
);

$tpl->assign('form', $array_rule_form);

$tpl->display("form.ihtml");

?>
