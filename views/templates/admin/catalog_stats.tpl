{**
 * Josra Shopping Feed — cómo está el catálogo (paso 3 del asistente)
 *}
<div class="panel">
  <div class="panel-heading"><i class="icon-bar-chart"></i> Tu catálogo: {$jss_total|intval} productos activos</div>
  <div class="row">
    {foreach from=$jss_stats item=s}
      <div class="col-sm-6 col-lg-3">
        <p style="margin-bottom:4px"><strong>{$s.label|escape:'html':'UTF-8'}</strong> <span class="pull-right">{$s.n|intval} ({$s.pct|intval} %)</span></p>
        <div class="progress" style="margin-bottom:4px">
          <div class="progress-bar {if $s.pct >= 90}progress-bar-success{elseif $s.pct >= 50}progress-bar-warning{else}progress-bar-danger{/if}" style="width:{$s.pct|intval}%"></div>
        </div>
        <p class="help-block">{$s.note|escape:'html':'UTF-8'}</p>
      </div>
    {/foreach}
  </div>
  {if !$jss_img_ok}
    <div class="alert alert-warning" style="margin-bottom:0">El tamaño de imagen elegido es menor de 500×500. Desde el 31/01/2027 Google no aceptará imágenes tan pequeñas: elige uno marcado con ✓.</div>
  {/if}
</div>
