{**
 * Josra Shopping Feed — resumen
 *}
<form method="post" action="{$jss_form_url|escape:'html':'UTF-8'}" style="margin-bottom:15px">
  <button type="submit" name="submitJssGenerate" value="1" class="btn btn-primary" data-jss-busy="Generando el feed…"><i class="icon-refresh"></i> Generar ahora</button>
</form>
{include file=$jss_tpl_dir|cat:'summary_body.tpl'}
