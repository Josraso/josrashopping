{**
 * Josra Shopping Feed — categorías de Google (paso 4 del asistente y pestaña Categorías)
 *}
<form method="post" action="{$jss_form_url|escape:'html':'UTF-8'}" class="form-horizontal" id="jss-cats-form" data-jss-ajax="{$jss_ajax_url|escape:'html':'UTF-8'}" data-jss-tax-file="{$jss_tax_file|escape:'html':'UTF-8'}">
  {if $jss_wizard}<input type="hidden" name="jss_step" value="{$jss_step|intval}">{/if}
  <div class="panel">
    <div class="panel-heading"><i class="icon-sitemap"></i> {if $jss_wizard}4. Categorías de Google{else}Categorías de Google{/if}</div>
    <div class="alert alert-info">
      Escribe en cada categoría de tu tienda qué es y elige la categoría de Google de la lista. Con dos palabras afinas más: «casco moto», «neumático moto», «recambios moto».
      Las subcategorías heredan la de su categoría superior si no tienen una propia: normalmente basta con poner las principales.
      {if $jss_wizard}Este paso es opcional: si lo saltas, Google clasifica los productos por su cuenta.{/if}
      <br>¿No encuentras la categoría? Mira la <a href="{$jss_tax_file|escape:'html':'UTF-8'}" target="_blank" rel="noopener">lista completa de categorías de Google</a> (con su número) o la <a href="{$jss_tax_help|escape:'html':'UTF-8'}" target="_blank" rel="noopener">ayuda de Google sobre categorías</a>, y escribe aquí el número.
    </div>
    <p>
      <button type="button" class="btn btn-default btn-sm" data-jss-tree="open"><i class="icon-plus-square"></i> Desplegar todo</button>
      <button type="button" class="btn btn-default btn-sm" data-jss-tree="close"><i class="icon-minus-square"></i> Plegar todo</button>
    </p>
    <div class="table-responsive" style="overflow:visible">
      <table class="table" id="jss-cats">
        <thead>
          <tr>
            <th>Categoría de la tienda</th>
            <th class="text-center">Productos</th>
            <th style="min-width:380px">Categoría de Google</th>
            <th>Enviar</th>
            <th>Género <span class="help-box" title="Solo ropa, calzado y complementos"></span></th>
            <th>Edad</th>
          </tr>
        </thead>
        <tbody>
        {foreach from=$jss_cats item=c}
          <tr data-jss-id="{$c.id|intval}" data-jss-parent="{$c.parent|intval}" data-jss-depth="{$c.depth|intval}"{if $c.depth > 1} style="display:none"{/if}>
            <td style="padding-left:{($c.depth-1)*22+8}px;white-space:nowrap">
              {if $c.children}
                <a href="#" class="jss-toggle" data-jss-toggle="{$c.id|intval}" title="Ver subcategorías"><i class="icon-caret-right"></i></a>
              {else}
                <span style="display:inline-block;width:10px"></span>
              {/if}
              {$c.name|escape:'html':'UTF-8'}
            </td>
            <td class="text-center">{if $c.products}{$c.products|intval}{else}<span class="text-muted">0</span>{/if}</td>
            <td style="position:relative">
              <input type="text" class="form-control jss-gcat" name="jss_gcat[{$c.id|intval}]" value="{$c.google|escape:'html':'UTF-8'}" autocomplete="off"
                     placeholder="{if $c.inherited}Hereda: {$c.inherited|escape:'html':'UTF-8'}{else}Escribe para buscar…{/if}">
            </td>
            <td>
              <select name="jss_export[{$c.id|intval}]" class="fixed-width-md">
                <option value=""{if $c.export === ''} selected{/if}>{if $c.depth > 1}Heredar ({if $c.export_eff}sí{else}no{/if}){else}Sí{/if}</option>
                {if $c.depth > 1}<option value="1"{if $c.export === '1'} selected{/if}>Sí</option>{/if}
                <option value="0"{if $c.export === '0'} selected{/if}>No enviar</option>
              </select>
            </td>
            <td>
              <select name="jss_gender[{$c.id|intval}]" class="fixed-width-sm">
                {foreach from=$jss_genders key=k item=v}<option value="{$k|escape:'html':'UTF-8'}"{if $c.gender == $k} selected{/if}>{$v|escape:'html':'UTF-8'}</option>{/foreach}
              </select>
            </td>
            <td>
              <select name="jss_age[{$c.id|intval}]" class="fixed-width-md">
                {foreach from=$jss_ages key=k item=v}<option value="{$k|escape:'html':'UTF-8'}"{if $c.age_group == $k} selected{/if}>{$v|escape:'html':'UTF-8'}</option>{/foreach}
              </select>
            </td>
          </tr>
        {foreachelse}
          <tr><td colspan="6" class="text-center text-muted">La tienda no tiene categorías.</td></tr>
        {/foreach}
        </tbody>
      </table>
    </div>
    <div class="panel-footer">
      {if $jss_wizard}
        <button type="submit" name="jss_back" value="1" class="btn btn-default pull-left"><i class="icon-arrow-left"></i> Atrás</button>
        <button type="submit" name="submitJssStep" value="1" class="btn btn-default pull-right"><i class="icon-arrow-right"></i> Siguiente</button>
        <button type="submit" name="jss_skip" value="1" class="btn btn-default pull-right" style="margin-right:6px"><i class="icon-share"></i> Saltar este paso</button>
      {else}
        <button type="submit" name="submitJssCategories" value="1" class="btn btn-default pull-right"><i class="icon-save"></i> Guardar</button>
      {/if}
    </div>
  </div>
</form>
