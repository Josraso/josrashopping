{**
 * Josra Shopping Feed — pestañas de la pantalla principal
 *}
<ul class="nav nav-tabs" style="margin-bottom:15px">
  {foreach from=$jss_tabs key=k item=t}
    <li class="{if $k == $jss_view}active{/if}">
      <a href="{$t[2]|escape:'html':'UTF-8'}"><i class="{$t[1]|escape:'html':'UTF-8'}"></i> {$t[0]|escape:'html':'UTF-8'}{if $t[3]} <span class="badge">{$t[3]|intval}</span>{/if}</a>
    </li>
  {/foreach}
</ul>
