<h2>{l s='Paycorp Payments' mod='paycorp'}</h2>
<form action="{$action}" method="post">
    <fieldset>
        <legend><img src="../img/admin/contact.gif" />{l s='Settings' mod='paycorp'}</legend>
        <table border="0" width="500" cellpadding="0" cellspacing="0" id="form">
            <tr>
                <td colspan="2">Please specify PayCorp Merchant details.<br /><br />
                </td>
            </tr>
            <tr>
                <td width="130">{l s='PG Domain' mod='paycorp'}</td>
                <td><input type="text" name="pg_domain" value="{$pg_domain}" style="width: 300px;" /></td>
            </tr>
            <tr>
                <td width="130" style="vertical-align: top;">{l s='Client ID' mod='paycorp'}</td>
                <td><input type="text" name="client_id" value="{$client_id}" style="width: 300px;" /></td>
            </tr>
            <tr>
                <td width="130" style="vertical-align: top;">{l s='HMAC Secret' mod='paycorp'}</td>
                <td><input type="text" name="hmac_secret" value="{$hmac_secret}" style="width: 300px;" /></td>
            </tr>
            <tr>
                <td width="130" style="vertical-align: top;">{l s='Auth Token' mod='paycorp'}</td>
                <td><input type="text" name="auth_token" value="{$auth_token}" style="width: 300px;" /></td>
            </tr>

            <tr><td colspan="2" align="center"><input class="button" name="Paycorp_UpdateSettings" value="Update settings" type="submit" /></td></tr>
        </table>
    </fieldset>
</form>
