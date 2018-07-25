<script type='text/javascript' src="./modules/centreon-awie/core/js/Export.js"></script>

<form name="exportForm" id="exportForm" enctype="multipart/form-data">

    <div class="loadingWrapper" style="display: none">
        {include file='loading.tpl'}
    </div>

    <table id="exportTab" class="formTable table">
        <tr class="ListHeader">
            <td class="FormHeader">
                <h3>| Export objects:</h3>
            </td>
        </tr>
        <tr class="list_lvl_1">
            <td class="ListColLvl1_name" colspan="2">
                <h4>Pollers</h4>
            </td>
        </tr>
        <tr class="list_one">
            <td class="FormRowField">Pollers</td>
            <td class="FormRowValue">
                <input onclick="selectFilter('INSTANCE');" name="export_INSTANCE[INSTANCE]" type="checkbox"
                       id="poller"/>
                <label for="poller">All</label>
                <span style="margin: 0 15px;vertical-align: middle;">or</span>
                <label for="poller1">Filter </label>
                <input type="text" id="poller1" placeholder="Ex: name" name="export_INSTANCE[INSTANCE_filter]" />
            </td>
        </tr>
        <tr class="list_lvl_1">
            <td class="ListColLvl1_name" colspan="2">
                <h4>Hosts</h4>
            </td>
        </tr>
        <tr class="list_two">
            <td class="FormRowField">Hosts</td>
            <td class="FormRowValue">
                <input onclick="selectFilter('HOST');" name="export_HOST[HOST]" type="checkbox" id="host"/>
                <label for="host">All</label>
                <span style="margin: 0 15px;vertical-align: middle;">or</span>
                <label for="host1">Filter </label>
                <input id="host1" placeholder="Ex: name" name="export_HOST[HOST_filter]" type="text"/>
            </td>
        </tr>
        <tr class="list_one">
            <td class="FormRowField">Host templates</td>
            <td class="FormRowValue">
                <input onclick="selectFilter('HTPL');" name="export_HTPL[HTPL]" type="checkbox" id="htpl"/>
                <label for="htpl">All</label>
                <span style="margin: 0 15px;vertical-align: middle;">or</span>
                <label for="htpl1">Filter </label>
                <input id="htpl1" placeholder="Ex: name" name="export_HTPL[HTPL_filter]" type="text"/>
            </td>
        </tr>
        <tr class="list_two">
            <td class="FormRowField">Host groups</td>
            <td class="FormRowValue">
                <input onclick="selectFilter('HG');" name="export_HG[HG]" type="checkbox" id="hg"/>
                <label for="hg">All</label>
                <span style="margin: 0 15px;vertical-align: middle;">or</span>
                <label for="hg1">Filter </label>
                <input id="hg1" placeholder="Ex: name" name="export_HG[HG_filter]" type="text"/>
            </td>
        </tr>
        <tr class="list_one">
            <td class="FormRowField">Host categories</td>
            <td class="FormRowValue">
                <input name="HC" type="checkbox" id="host_c"/>
                <label for="host_c">Host Categories</label>
            </td>
        </tr>
        <tr class="list_lvl_1">
            <td class="ListColLvl1_name" colspan="2">
                <h4>Services</h4>
            </td>
        </tr>
        <tr class="list_one">
            <td class="FormRowField">Services</td>
            <td class="FormRowValue">
                <input onclick="selectFilter('SERVICE');" name="export_SERVICE[SERVICE]" type="checkbox" id="svc"/>
                <label for="svc">All</label>
                <span style="margin: 0 15px;vertical-align: middle;">or</span>
                <label for="service1">Filter </label>
                <input id="service1" placeholder="Ex: name" name="export_SERVICE[SERVICE_filter]" type="text"/>
            </td>
        </tr>
        <tr class="list_two">
            <td class="FormRowField">Service templates</td>
            <td class="FormRowValue">
                <input onclick="selectFilter('STPL');" name="export_STPL[STPL]" type="checkbox" id="stpl"/>
                <label for="stpl">All</label>
                <span style="margin: 0 15px;vertical-align: middle;">or</span>
                <label for="stpl1">Filter </label>
                <input id="stpl1" placeholder="Ex: name" name="export_STPL[STPL_filter]" type="text"/>
            </td>
        </tr>
        <tr class="list_one">
            <td class="FormRowField">Service groups</td>
            <td class="FormRowValue">
                <input onclick="selectFilter('SG');" name="export_SG[SG]" type="checkbox" id="sg"/>
                <label for="sg">All</label>
                <span style="margin: 0 15px;vertical-align: middle;">or</span>
                <label for="sg1">Filter </label>
                <input id="sg1" placeholder="Ex: name" name="export_SG[SG_filter]" type="text"/>
            </td>
        </tr>
        <tr class="list_two">
            <td class="FormRowField">Service categories</td>
            <td class="FormRowValue">
                <input name="SC" type="checkbox" id="svc_c"/>
                <label for="svc_c">Service Categories</label>
            </td>
        </tr>
        <tr class="list_lvl_1">
            <td class="ListColLvl1_name" >
                <h4>Contacts</h4>
            </td>
            <td class="FormRowValue" >
                <input name="CONTACT" type="checkbox" id="contact"/>
                <label for="contact">Contacts</label>
                <input name="CG" type="checkbox" id="cgroup"/>
                <label for="cgroup">Contactgroups</label>
            </td>
        </tr>
        <tr class="list_lvl_1">
            <td class="ListColLvl1_name" >
                <h4>Commands</h4>
            </td>
            <td class="FormRowValue">
                <input name="export_cmd[c_cmd]" type="checkbox" id="c_cmd"/>
                <label for="c_cmd">Check CMD</label>
                <input name="export_cmd[n_cmd]" type="checkbox" id="n_cmd"/>
                <label for="n_cmd">Notification CMD</label>
                <input name="export_cmd[m_cmd]" type="checkbox" id="m_cmd"/>
                <label for="m_cmd">Misc CMD</label>
                <input name="export_cmd[d_cmd]" type="checkbox" id="d_cmd"/>
                <label for="d_cmd">Discovery CMD</label>
            </td>
        </tr>
        <tr class="list_one">

        </tr>
        <tr class="list_lvl_1">
            <td class="ListColLvl1_name" >
                <h4>Resources</h4>
            </td>
            <td class="FormRowValue">
                <input name="ACL" type="checkbox" id="acl"/>
                <label for="acl">ACL</label>
                <input name="LDAP" type="checkbox" id="ldap"/>
                <label for="ldap">LDAP</label>
                <input name="TP" type="checkbox" id="tp"/>
                <label for="tp">Timeperiods</label>
            </td>
        </tr>
    </table>

    <div id="validForm">
        <p><input onclick="submitForm();" class="btc bt_success" name="submitC" value="Export" type="button"/></p>
    </div>
</form>

<form name="downloadForm" id="downloadForm" method="post" action="{$formPath}" enctype="multipart/form-data">
    <input name="pathFile" id="pathFile" type="hidden"/>
</form>
