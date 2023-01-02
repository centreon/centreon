<script type='text/javascript' src="./modules/centreon-awie/core/js/Import.js"></script>

<form method="post" id="importForm" name="importForm" enctype="multipart/form-data">
    <div class="loadingWrapper" style="display: none">
        {include file='loading.tpl'}
    </div>

    <table id="exportTab" class="formTable table">
        <tr class="ListHeader">
            <td class="FormHeader" colspan="2">
                <h3>| Import objects</h3>
            </td>
        </tr>
        <tr class="list_one">
            <td class="FormRowValue" colspan="2">
                <div class="msg-wrapper msg-center" style="display: none;">
                </div>
            </td>
        </tr>
        <tr class="list_two">
            <td class="FormRowField">
                <h4>Import zip archive <span class="red">*</span></h4>
            </td>
            <td class="FormRowValue">
                <input onchange="checkSize(this);" type="file" id="file" name="clapiImport" required />
            </td>
        </tr>
    </table>
    <div id="validForm">
        <p><input onclick="submitForm();" class="btc bt_success" value="Import" type="button"/></p>
    </div>

</form>
