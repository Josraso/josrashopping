{**
 * Josra Shopping Feed — último paso del asistente
 *}
{include file=$jss_tpl_dir|cat:'summary_body.tpl'}
<div class="panel">
  <form method="post" action="{$jss_regen_url|escape:'html':'UTF-8'}">
    <div class="panel-footer" style="margin:0">
      <a href="{$jss_back_url|escape:'html':'UTF-8'}" class="btn btn-default pull-left"><i class="icon-arrow-left"></i> Atrás</a>
      <a href="{$jss_regen_url|escape:'html':'UTF-8'}" class="btn btn-default" data-jss-busy="Generando el feed…"><i class="icon-refresh"></i> Volver a generar</a>
      <button type="submit" name="submitJssFinish" value="1" class="btn btn-primary pull-right"><i class="icon-check"></i> Terminar</button>
    </div>
  </form>
</div>
