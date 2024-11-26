<?php

/*
 * Copyright 2005-2021 Centreon
 * Centreon is developed by : Julien Mathis and Romain Le Merlus under
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
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

if (!isset($centreon)) {
    exit();
}

require_once "./include/common/common-Func.php";
require_once "./class/centreonMsg.class.php";

if (isset($_GET["o"]) && $_GET["o"] === 'r') {
    $msg = new CentreonMsg();
    $sid = session_id();
    try {
        $statement = $pearDB->prepareQuery(
            <<<SQL
                UPDATE session SET update_acl = '1' WHERE session_id = :sessionId
                SQL
        );
        $pearDB->executePreparedQuery($statement, ['sessionId' => $sid]);
        $pearDB->executeQuery(
            <<<SQL
                UPDATE acl_resources SET changed = '1'
                SQL
        );
        $msg->info(_("ACL reloaded"));
        passthru(_CENTREON_PATH_ . "/cron/centAcl.php");
    } catch (CentreonDbException|\PDOException $e) {
        $msg->error(_("An error occurred"));
    }
} elseif (isset($_POST["o"]) && $_POST["o"] === 'u') {
    $msg = new CentreonMsg();
    $userIds = filter_var_array(
        $_GET["select"] ?? $_POST["select"] ?? [],
        FILTER_VALIDATE_INT
    );

    try {
        $pearDB->beginTransaction();
        $pearDB->executeQuery(
            <<<SQL
                UPDATE acl_resources SET changed = '1'
                SQL
        );
        $bindParams = [];
        // Remove false values from filtered array.
        foreach (array_keys(array_filter($userIds)) as $userId) {
            $bindParams[':user_' . $userId] = $userId;
        }
        if ([] !== $bindParams) {
            $userIdAsString = implode(', ', array_keys($bindParams));
            $statement = $pearDB->prepareQuery(
                <<<SQL
                    UPDATE session SET update_acl = '1' WHERE user_id IN ($userIdAsString)
                    SQL
            );
            $pearDB->executePreparedQuery($statement, $bindParams);
        }
        $pearDB->commit();

        $msg->info(_("ACL reloaded"));
        passthru(_CENTREON_PATH_ . "/cron/centAcl.php");
    } catch (CentreonDbException|\PDOException $e) {
        $pearDB->rollBack();
        $msg->error(_("An error occurred"));
    }
}

# Smarty template Init
$tpl = new Smarty();
$tpl = initSmartyTpl(__DIR__, $tpl);

$res = $pearDB->executeQuery(
    <<<SQL
        SELECT DISTINCT * FROM session
        SQL
);

$session_data = [];
$cpt = 0;
$form = new HTML_QuickFormCustom('select_form', 'POST', "?p=" . $p);

while ($r = $res->fetch()) {
    $statement = $pearDB->prepareQuery(
        "SELECT contact_name, contact_admin FROM contact
        WHERE contact_id = :contactId"
    );

    $pearDB->executePreparedQuery($statement, ['contactId' => $r["user_id"]]);
    $rU = $statement->fetch();
    if ($rU["contact_admin"] != "1") {
        $session_data[$cpt] = array();
        if ($cpt % 2) {
            $session_data[$cpt]["class"] = "list_one";
        } else {
            $session_data[$cpt]["class"] = "list_two";
        }
        $session_data[$cpt]["user_id"] = $r["user_id"];
        $session_data[$cpt]["user_alias"] = $rU["contact_name"];
        $session_data[$cpt]["admin"] = $rU["contact_admin"];

        /**
         * Set current page and topology only if they are not null to avoid invalid array offsets.
         */
        $currentPage = null;
        $topologyName = null;
        if ($r["current_page"] !== null) {
            $statement = $pearDB->prepareQuery(
                <<<SQL
                    SELECT topology_name, topology_page, topology_url_opt FROM topology
                    WHERE topology_page = :topologyPage
                    SQL
            );
            $pearDB->executePreparedQuery($statement, ['topologyPage' => $r["current_page"]]);
            $rCP = $statement->fetch();
            $currentPage = $rCP["topology_name"] . $rCP["topology_url_opt"];
            $topologyName = _($rCP["topology_name"]);
        }
        $session_data[$cpt]["current_page"] = $currentPage;
        $session_data[$cpt]["topology_name"] = $topologyName;
        $session_data[$cpt]["ip_address"] = $r["ip_address"];
        $session_data[$cpt]["actions"] = "<a href='./main.php?p=" . $p . "&o=r'>" .
            returnSvg(
                "www/img/icons/refresh.svg",
                "var(--help-tool-tip-icon-fill-color)",
                18,
                18
            ) . "</a>";
        $selectedElements = $form->addElement('checkbox', "select[" . $r['user_id'] . "]");
        $session_data[$cpt]["checkbox"] = $selectedElements->toHtml();
        $cpt++;
    }
}
if (isset($msg)) {
    $tpl->assign("msg", $msg);
}

$tpl->assign("session_data", $session_data);
$tpl->assign("wi_user", _("Connected users"));
$tpl->assign("wi_where", _("Position"));
$tpl->assign("actions", _("Reload ACL"));
$tpl->assign("distant_location", _("IP Address"));
?>
<script type="text/javascript">
function setO(_i) {
    document.forms['form'].elements['o'].value = _i;
}
</script>
<?php
foreach (array('o1', 'o2') as $option) {
    $attrs = array(
        'onchange'=>"javascript: " .
            "if (this.form.elements['" . $option . "'].selectedIndex == 1) " .
            "{setO(this.form.elements['" . $option . "'].value); submit();} " .
            "this.form.elements['" . $option . "'].selectedIndex = 0");
    $form->addElement('select', $option, null, array(null=>_("More actions..."), "u"=>_("Reload ACL")), $attrs);
    $o1 = $form->getElement($option);
    $o1->setValue(null);
}

$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->assign('p', $p);
$tpl->display("reloadACL.ihtml");
