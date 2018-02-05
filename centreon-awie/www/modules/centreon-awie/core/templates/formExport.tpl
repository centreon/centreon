<form {$form.attributes}>
    <table class="formTable table">
        <tr class="list_lvl_1">
            <td class="ListColLvl1_name" colspan="2">
                <h4>{$form.header.title}</h4>
            </td>
        </tr>
        <tr class="list_one">
            <td class="FormRowField"><img class="helpTooltip" name="notification_options"> {$form.export_all.label}
            </td>
            <td class="FormRowValue">{$form.export_all.html}</td>
        </tr>
        <tr class="list_two">
            <td class="FormRowField"></td>
            <td class="FormRowValue">{$form.export_cmd.html}</td>
        </tr>
        <tr class="list_one">
            <td class="FormRowField"></td>
            <td class="FormRowValue">{$form.simple_export.html}</td>
        </tr>
        <tr class="list_two">
            <td class="FormRowField"></td>
            <td class="FormRowValue">{$form.host.html} {$form.host_filter.html}</td>
        </tr>
        <tr class="list_one">
            <td class="FormRowField"></td>
            <td class="FormRowValue">{$form.htpl.html} {$form.htpl_filter.html}</td>
        </tr>
        <tr class="list_two">
            <td class="FormRowField"></td>
            <td class="FormRowValue">{$form.host_c.html} </td>
        </tr>
        <tr class="list_one">
            <td class="FormRowField"></td>
            <td class="FormRowValue">{$form.svc.html} {$form.svc_filter.html}</td>
        </tr>
        <tr class="list_two">
            <td class="FormRowField"></td>
            <td class="FormRowValue">{$form.stpl.html} {$form.stpl_filter.html}</td>
        </tr>
        <tr class="list_one">
            <td class="FormRowField"></td>
            <td class="FormRowValue">{$form.export_connect.html}</td>
        </tr>
        <tr class="list_two">
            <td class="FormRowField"></td>
            <td class="FormRowValue">{$form.poller.html} {$form.poller_filter.html}</td>
        </tr>
    </table>

    {if !$valid}
        <div id="validForm">
            <p>{$form.submitC.html}{$form.submitA.html}</p>
        </div>
    {else}
        <div id="validForm">
            <p>{$form.change.html}</p>
        </div>
    {/if}

    {$form.hidden}
</form>
{$helpText}