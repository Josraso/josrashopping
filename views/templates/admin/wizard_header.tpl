{**
 * Josra Shopping Feed — cabecera del asistente
 *}
<div class="panel">
  <div class="panel-heading">
    <i class="icon-magic"></i> Asistente de configuración
    {if $jss_done}<a class="btn btn-default btn-xs pull-right" href="{$jss_exit_url|escape:'html':'UTF-8'}"><i class="icon-remove"></i> Salir del asistente</a>{/if}
  </div>
  <ul class="nav nav-pills nav-justified">
    {foreach from=$jss_steps key=n item=title}
      <li class="{if $n == $jss_step}active{elseif $n < $jss_step}{else}disabled{/if}">
        {if $jss_step_urls[$n] && $n != $jss_step}
          <a href="{$jss_step_urls[$n]|escape:'html':'UTF-8'}">{if $n < $jss_step}<i class="icon-check"></i>{else}<span class="badge">{$n|intval}</span>{/if} {$title|escape:'html':'UTF-8'}</a>
        {else}
          <a href="#" onclick="return false;"><span class="badge">{$n|intval}</span> {$title|escape:'html':'UTF-8'}</a>
        {/if}
      </li>
    {/foreach}
  </ul>
  <div class="progress" style="margin:15px 0 0;height:6px">
    <div class="progress-bar progress-bar-success" style="width:{math equation='x*100/y' x=$jss_step y=$jss_steps|@count format='%d'}%"></div>
  </div>
</div>
