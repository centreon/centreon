
{assign var="default_separator" value=" "}

{if !isset($separator) }
    {assign var="separator" value="$default_separator"}
{/if}

{assign var="separator_tmp" value=""}
{if $host_selected|@count gt 0}
{foreach from=$host_selected item=host}
{$separator_tmp}{$host.name}/{$host.state_str}
{assign var="separator_tmp" value="$separator"}
{/foreach}
{/if}

{assign var="separator_tmp" value=""}
{if $service_selected|@count gt 0}
{foreach from=$service_selected item=service}
{$separator_tmp}{$service.host_name}/{$service.description}/{$service.state_str}
{assign var="separator_tmp" value="$separator"}
{/foreach}
{/if}
