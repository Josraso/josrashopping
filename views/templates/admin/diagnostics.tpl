{**
 * Josra Shopping Feed — diagnóstico
 *}
<div class="panel">
  <div class="panel-heading"><i class="icon-stethoscope"></i> Diagnóstico{if $jss_run_date} <small>(última generación: {$jss_run_date|escape:'html':'UTF-8'})</small>{/if}</div>
  <p class="help-block">Lo que Google va a rechazar o marcar con avisos, antes de que lo haga. Se actualiza cada vez que se genera el feed: arregla los productos y pulsa «Generar ahora» en el Resumen.</p>
  <div class="table-responsive">
    <table class="table">
      <thead><tr><th>Problema</th><th>Qué pasa y cómo arreglarlo</th><th class="text-center">Artículos</th><th></th></tr></thead>
      <tbody>
      {foreach from=$jss_types item=t}
        <tr{if $t.code == $jss_code} class="active"{/if}>
          <td style="white-space:nowrap">
            {if $t.level == 2}<span class="label label-danger">No se envía</span>{elseif $t.level == 1}<span class="label label-warning">Aviso</span>{else}<span class="label label-info">Sugerencia</span>{/if}
            <strong>{$t.label|escape:'html':'UTF-8'}</strong>
          </td>
          <td>{$t.help|escape:'html':'UTF-8'}</td>
          <td class="text-center">{if $t.n}<span class="badge">{$t.n|intval}</span>{else}<i class="icon-check text-success"></i>{/if}</td>
          <td class="text-right">{if $t.n}<a href="{$t.url|escape:'html':'UTF-8'}" class="btn btn-default btn-xs"><i class="icon-search"></i> Ver</a>{/if}</td>
        </tr>
      {/foreach}
      </tbody>
    </table>
  </div>
</div>

{if $jss_code}
<div class="panel">
  <div class="panel-heading"><i class="icon-list"></i> {$jss_code_label|escape:'html':'UTF-8'}</div>
  <p class="help-block">{$jss_code_help|escape:'html':'UTF-8'}</p>
  <div class="table-responsive">
    <table class="table">
      <thead><tr><th>ID</th><th>Producto</th><th>Referencia</th><th>Combinación</th><th>Detalle</th><th></th></tr></thead>
      <tbody>
      {foreach from=$jss_rows item=r}
        <tr>
          <td>{$r.id|intval}</td>
          <td>{$r.name|escape:'html':'UTF-8'}</td>
          <td>{$r.reference|escape:'html':'UTF-8'}</td>
          <td>{$r.combination|escape:'html':'UTF-8'}</td>
          <td>{$r.detail|escape:'html':'UTF-8'}</td>
          <td class="text-right"><a href="{$r.edit|escape:'html':'UTF-8'}" target="_blank" class="btn btn-default btn-xs"><i class="icon-pencil"></i> Editar</a></td>
        </tr>
      {foreachelse}
        <tr><td colspan="6" class="text-center text-muted">Ninguno.</td></tr>
      {/foreach}
      </tbody>
    </table>
  </div>
  {if $jss_page_urls|@count > 1}
    <ul class="pagination">
      {foreach from=$jss_page_urls key=n item=u}
        <li{if $n == $jss_page} class="active"{/if}><a href="{$u|escape:'html':'UTF-8'}">{$n|intval}</a></li>
      {/foreach}
    </ul>
  {/if}
</div>
{/if}
