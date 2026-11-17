{**
 * Josra Shopping Feed — categorías de Google (paso 4 del asistente y pestaña Categorías)
 *}
<form method="post" action="{$jss_form_url|escape:'html':'UTF-8'}" class="form-horizontal" id="jss-cats-form" data-jss-ajax="{$jss_ajax_url|escape:'html':'UTF-8'}">
  {if $jss_wizard}<input type="hidden" name="jss_step" value="{$jss_step|intval}">{/if}
  <div class="panel">
    <div class="panel-heading"><i class="icon-sitemap"></i> {if $jss_wizard}4. Categorías de Google{else}Categorías de Google{/if}</div>
    <div class="alert alert-info">
      Escribe en cada categoría de tu tienda qué es y elige la categoría de Google de la lista. Con dos palabras afinas más: «casco moto», «neumático moto», «zapatillas deporte».
      Las subcategorías heredan la de su categoría superior si no tienen una propia.
      {if $jss_wizard}Este paso es opcional: si lo saltas, Google clasifica los productos por su cuenta. Aquí salen solo las categorías principales; las subcategorías se ajustan luego en la pestaña «Categorías de Google».{/if}
    </div>
    <datalist id="jss-tax-list"></datalist>
    <div class="table-responsive">
      <table class="table">
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
          <tr>
            <td style="padding-left:{($c.depth-1)*22+8}px">{if $c.depth > 1}<span class="text-muted">└ </span>{/if}{$c.name|escape:'html':'UTF-8'}</td>
            <td class="text-center">{$c.products|intval}</td>
            <td>
              <input type="text" class="form-control jss-gcat" name="jss_gcat[{$c.id|intval}]" value="{$c.google|escape:'html':'UTF-8'}" list="jss-tax-list" autocomplete="off"
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
