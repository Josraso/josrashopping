{**
 * Josra Shopping Feed — feeds, última generación y alta en Merchant Center (resumen y último paso)
 *}
<div class="panel">
  <div class="panel-heading"><i class="icon-rss"></i> {if $jss_finish}Tu feed está listo{else}Tus feeds{/if}</div>
  {if !empty($jss_run.error)}
    <div class="alert alert-danger">No se pudo generar: {$jss_run.error|escape:'html':'UTF-8'}</div>
  {/if}
  {if $jss_run_date}
    <div class="row" style="margin-bottom:15px">
      <div class="col-sm-3"><div class="alert alert-success" style="margin:0;text-align:center"><span style="font-size:24px;font-weight:bold">{$jss_items|intval}</span><br>artículos enviados</div></div>
      <div class="col-sm-3"><div class="alert alert-info" style="margin:0;text-align:center"><span style="font-size:24px;font-weight:bold">{$jss_products|intval}</span><br>productos</div></div>
      <div class="col-sm-3"><div class="alert {if $jss_warnings}alert-warning{else}alert-success{/if}" style="margin:0;text-align:center"><span style="font-size:24px;font-weight:bold">{$jss_warnings|intval}</span><br>avisos</div></div>
      <div class="col-sm-3"><div class="alert alert-info" style="margin:0;text-align:center"><span style="font-size:24px;font-weight:bold">{$jss_run_date|escape:'html':'UTF-8'}</span><br>última generación ({$jss_run.seconds|escape:'html':'UTF-8'} s)</div></div>
    </div>
    {if $jss_excluded}
      <p><strong>No enviados:</strong>
        {foreach from=$jss_excluded item=e name=ex}{$e.label|escape:'html':'UTF-8'}: {$e.n|intval}{if !$smarty.foreach.ex.last} · {/if}{/foreach}
      </p>
    {/if}
    {if $jss_issue_total}
      <p><a href="{$jss_diag_url|escape:'html':'UTF-8'}" class="btn btn-default btn-sm"><i class="icon-stethoscope"></i> Ver los {$jss_issue_total|intval} avisos en el diagnóstico</a></p>
    {/if}
  {else}
    <div class="alert alert-info">Todavía no se ha generado el feed. Pulsa «Generar ahora».</div>
  {/if}

  <div class="table-responsive">
    <table class="table">
      <thead><tr><th>Idioma</th><th>Moneda</th><th>URL del feed (para Merchant Center)</th><th>Actualizado</th><th>Tamaño</th></tr></thead>
      <tbody>
      {foreach from=$jss_feeds item=f}
        <tr>
          <td>{$f.lang_name|escape:'html':'UTF-8'}</td>
          <td>{$f.currency|escape:'html':'UTF-8'}</td>
          <td style="min-width:360px">
            <div class="input-group">
              <input type="text" class="form-control" readonly value="{$f.url|escape:'html':'UTF-8'}" id="jss-url-{$f.lang|escape:'html':'UTF-8'}">
              <span class="input-group-btn"><button type="button" class="btn btn-default" data-jss-copy="jss-url-{$f.lang|escape:'html':'UTF-8'}"><i class="icon-copy"></i> Copiar</button></span>
            </div>
          </td>
          <td>{if $f.date}{$f.date|escape:'html':'UTF-8'}{else}—{/if}</td>
          <td>{if $f.size}{$f.size|escape:'html':'UTF-8'}{else}—{/if}</td>
        </tr>
      {/foreach}
      </tbody>
    </table>
  </div>
  <p class="help-block">El feed se regenera solo cuando Google lo lee y tiene más de {$jss_refresh|intval} {if $jss_refresh == 1}hora{else}horas{/if}. No necesitas ninguna tarea programada.</p>
  {if !$jss_img_ok}
    <div class="alert alert-warning">El tamaño de imagen elegido es menor de 500×500: desde el 31/01/2027 Google lo rechazará. Cámbialo en Configuración &gt; Datos del producto.</div>
  {/if}
</div>

<div class="panel">
  <div class="panel-heading"><i class="icon-google"></i> Dar de alta el feed en Google Merchant Center</div>
  <ol>
    <li>Entra en <a href="https://merchants.google.com/" target="_blank" rel="noopener">merchants.google.com</a> con la cuenta de Google de la tienda (si no tienes cuenta, créala y verifica tu web).</li>
    <li>Ve a <strong>Productos</strong> &gt; <strong>Añadir productos</strong> (o <em>Fuentes de datos</em>) y elige <strong>Añadir productos desde un archivo</strong>.</li>
    <li>Elige <strong>Introducir un enlace a tu archivo</strong> (obtención programada) y pega la URL del feed de arriba.</li>
    <li>Como país de venta elige <strong>{$jss_country_name|escape:'html':'UTF-8'}</strong> y el idioma del feed. Programa la obtención <strong>una vez al día</strong>.</li>
    {if $jss_shipping == 'account'}
      <li>En <strong>Configuración</strong> &gt; <strong>Envío y devoluciones</strong> crea una política de envío con tus precios (has elegido configurarlo allí).</li>
    {/if}
    <li>En unas horas Google revisa los productos. Lo que rechace sale en <strong>Productos</strong> &gt; <strong>Necesita atención</strong>; aquí, en <strong>Diagnóstico</strong>, ves antes los problemas más habituales.</li>
  </ol>
</div>

<div class="panel">
  <div class="panel-heading"><i class="icon-time"></i> Regenerar con una tarea programada (opcional)</div>
  <p>Si prefieres regenerar el feed a una hora fija (por ejemplo, de noche en catálogos muy grandes), crea una tarea programada en Plesk que abra esta URL:</p>
  <div class="input-group">
    <input type="text" class="form-control" readonly value="{$jss_cron|escape:'html':'UTF-8'}" id="jss-cron-url">
    <span class="input-group-btn"><button type="button" class="btn btn-default" data-jss-copy="jss-cron-url"><i class="icon-copy"></i> Copiar</button></span>
  </div>
  <p class="help-block">En Plesk: Herramientas y configuración &gt; Tareas programadas &gt; Añadir tarea &gt; «Recuperar una URL». Por línea de comandos: <code>curl -s "{$jss_cron|escape:'html':'UTF-8'}"</code></p>
</div>
