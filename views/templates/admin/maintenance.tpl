{**
 * Josra Shopping Feed — asistente y reiniciar
 *}
<div class="panel">
  <div class="panel-heading"><i class="icon-wrench"></i> Asistente y reinicio</div>
  <div class="row">
    <div class="col-md-6">
      <p><strong>Volver a lanzar el asistente</strong></p>
      <p class="help-block">Repasa la configuración paso a paso. No borra nada: parte de lo que tienes guardado.</p>
      <a href="{$jss_wizard_url|escape:'html':'UTF-8'}" class="btn btn-default"><i class="icon-magic"></i> Abrir el asistente</a>
    </div>
    <div class="col-md-6">
      <p><strong>Reiniciar el módulo</strong></p>
      <p class="help-block">Borra los ajustes, las categorías de Google asignadas, el diagnóstico y los feeds generados de esta tienda, y vuelve a empezar con el asistente. Las URLs del feed se mantienen.</p>
      <form method="post" action="{$jss_form_url|escape:'html':'UTF-8'}" onsubmit="return confirm('¿Reiniciar el módulo? Se borra toda su configuración en esta tienda (el feed seguirá en la misma URL).');">
        <button type="submit" name="submitJssReset" value="1" class="btn btn-danger"><i class="icon-eraser"></i> Reiniciar el módulo</button>
      </form>
    </div>
  </div>
</div>
