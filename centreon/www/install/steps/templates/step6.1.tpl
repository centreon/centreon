<form id='form_step6'>
    <table cellpadding='0' cellspacing='0' border='0' width='100%' class='StyleDottedHr' align='center'>
        <thead>
        <tr>
            <th colspan='2'>{t}VAULT INFORMATION{/t}</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td class='formlabel'>{t}Vault Address{/t} <span style='color:#e00b3d'> *</span></td>
            <td class='formvalue'>
                <input type='text' name='address' value='{$parameters.address}' size="35"/>
                <label class='field_msg'></label>
            </td>
        </tr>
        <tr>
            <td class='formlabel'>{t}Vault Port (default: 443){/t} <span style='color:#e00b3d'> *</span></td>
            <td class='formvalue'>
                <input type='text' name='port' value='{$parameters.port}' size="35"/>
                <label class='field_msg'></label>
            </td>
        </tr>
        <tr>
            <td class='formlabel'>{t}Centreon Storage Path{/t} <span style='color:#e00b3d'> *</span></td>
            <td class='formvalue'>
                <input type='text' name='root_path' value='{$parameters.root_path}' size="35"/>
                <label class='field_msg'></label>
            </td>
        </tr>
        <tr>
            <td class='formlabel'>{t}Role Id{/t} <span style='color:#e00b3d'> *</span></td>
            <td class='formvalue'>
                <input type='text' name='role_id' value='{$parameters.role_id}' size="35"/>
                <label class='field_msg'></label>
            </td>
        </tr>
        <tr>
            <td class='formlabel'>{t}Secret Id{/t}<span style='color:#e00b3d'> *</span></td>
            <td class='formvalue'>
                <input type='text' name='secret_id' value='{$parameters.secret_id}' size="35"/>
                <label class='field_msg'></label>
            </td>
        </tr>
        </tbody>
    </table>
</form>

<script type="text/javascript">

    {literal}

    function validation() {
        jQuery('.field_msg').empty();

        jQuery.ajax({
            type: 'POST',
            url: './steps/process/process_step_vault.php',
            data: jQuery('#form_step6').serialize(),
            success: (data) => {
                var result = JSON.parse(data);
                if (!result.required.length && result.connection_error === '') {
                        loadStep("nextStep");
                } else {
                    result.required.forEach(function (element) {
                        jQuery("input[name=" + element + "]").next().html("Parameter is required");
                    });
                    if (result.connection_error !== '') {
                        jQuery('input[name="address"]').next().html(result.connection_error);
                    }
                }
            }
        });

        return false;
    }

    {/literal}

</script>