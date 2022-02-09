{if $status == 'ok'}
    <p>{l s='Your order on %s is complete.' sprintf=$shop_name mod='paycorp'}
        <br /><br /><span class="bold">{l s='Your order will be shipped as soon as possible.' mod='paycorp'}</span>
        <br /><br />{l s='For any questions or for further information, please contact our' mod='paycorp'} <a href="{$link->getPageLink('contact', true)|escape:'html'}">{l s='customer support' mod='paycorp'}</a>.
    </p>
{else}
    <p class="warning">
        {l s='We noticed a problem with your order. If you think this is an error, you can contact our' mod='paycorp'}
        <a href="{$link->getPageLink('contact', true)|escape:'html'}">{l s='customer support' mod='paycorp'}</a>.
        <br />
        {l s='Details: %s.' sprintf=$message mod='paycorp'}
    </p>
{/if}
