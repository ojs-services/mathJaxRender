{**
 * templates/settingsForm.tpl
 *
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * MathJax for OJS — settings form.
 *}

<script>
	$(function() {ldelim}
		// Attach the form handler.
		$('#mathJaxRenderSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');

		// The backend single-dollar option only applies while backend
		// rendering is enabled; keep it hidden otherwise.
		var toggleBackendOptions = function() {ldelim}
			$('#mathJaxRenderBackendOptions').toggle($('#areaBackend').prop('checked'));
		{rdelim};
		$('#areaBackend').on('change', toggleBackendOptions);
		toggleBackendOptions();
	{rdelim});
</script>
<form class="pkp_form mathJaxRender_ignore" id="mathJaxRenderSettingsForm" method="post" action="{url router=PKP\core\PKPApplication::ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
	{csrf}
	<p id="mathJaxRenderDescription">{translate key="plugins.generic.mathJaxRender.settings.description"}</p>
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="mathJaxRenderFormNotification"}

	{if $bundledFallbackActive}
		<p id="mathJaxRenderFallbackWarning" class="pkp_form_error">
			{translate key="plugins.generic.mathJaxRender.settings.source.fallbackWarning"}
		</p>
	{/if}

	{fbvFormArea id="mathJaxRenderAreasFormArea" title="plugins.generic.mathJaxRender.settings.areas"}
		{fbvFormSection list=true description="plugins.generic.mathJaxRender.settings.areas.description"}
			{fbvElement type="checkbox" id="areaArticle" checked=$areaArticle label="plugins.generic.mathJaxRender.settings.areaArticle"}
			{fbvElement type="checkbox" id="areaGalley" checked=$areaGalley label="plugins.generic.mathJaxRender.settings.areaGalley"}
			{fbvElement type="checkbox" id="areaIssue" checked=$areaIssue label="plugins.generic.mathJaxRender.settings.areaIssue"}
			{fbvElement type="checkbox" id="areaSearch" checked=$areaSearch label="plugins.generic.mathJaxRender.settings.areaSearch"}
			{fbvElement type="checkbox" id="areaHomepage" checked=$areaHomepage label="plugins.generic.mathJaxRender.settings.areaHomepage"}
			{fbvElement type="checkbox" id="areaBackend" checked=$areaBackend label="plugins.generic.mathJaxRender.settings.areaBackend"}
		{/fbvFormSection}
	{/fbvFormArea}

	<div id="mathJaxRenderBackendOptions">
		{fbvFormArea id="mathJaxRenderBackendFormArea" title="plugins.generic.mathJaxRender.settings.backend"}
			{fbvFormSection list=true description="plugins.generic.mathJaxRender.settings.backend.description"}
				{fbvElement type="checkbox" id="backendSingleDollar" checked=$backendSingleDollar label="plugins.generic.mathJaxRender.settings.backendSingleDollar"}
			{/fbvFormSection}
		{/fbvFormArea}
	</div>

	{fbvFormArea id="mathJaxRenderSourceFormArea" title="plugins.generic.mathJaxRender.settings.source"}
		{fbvFormSection}
			{* Raw radio inputs: fbvElement type="radio" is unreliable inside fbvFormSection. *}
			<ul class="checkbox_and_radiobutton">
				<li>
					<label>
						<input type="radio" name="source" id="sourceCdn" value="cdn"{if $source != 'bundled'} checked="checked"{/if} />
						{translate key="plugins.generic.mathJaxRender.settings.source.cdn"}
					</label>
				</li>
				<li>
					<label>
						{* Keep the radio submittable while it is the saved choice, even
						   with the files missing — disabling it would silently rewrite
						   the setting to CDN on save. *}
						<input type="radio" name="source" id="sourceBundled" value="bundled"{if $source == 'bundled'} checked="checked"{/if}{if !$bundledAvailable && $source != 'bundled'} disabled="disabled"{/if} />
						{translate key="plugins.generic.mathJaxRender.settings.source.bundled"}
						{if !$bundledAvailable} — {translate key="plugins.generic.mathJaxRender.settings.source.bundledMissing"}{/if}
					</label>
				</li>
			</ul>
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormArea id="mathJaxRenderDelimitersFormArea" title="plugins.generic.mathJaxRender.settings.delimiters"}
		{fbvFormSection list=true}
			{fbvElement type="checkbox" id="singleDollar" checked=$singleDollar label="plugins.generic.mathJaxRender.settings.singleDollar"}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormArea id="mathJaxRenderInputFormArea" title="plugins.generic.mathJaxRender.settings.inputFormats"}
		{fbvFormSection list=true description="plugins.generic.mathJaxRender.settings.inputFormats.description"}
			{fbvElement type="checkbox" id="inputTex" checked=true disabled=true label="plugins.generic.mathJaxRender.settings.inputTex"}
			{fbvElement type="checkbox" id="inputMathml" checked=$inputMathml label="plugins.generic.mathJaxRender.settings.inputMathml"}
			{fbvElement type="checkbox" id="inputAsciimath" checked=$inputAsciimath label="plugins.generic.mathJaxRender.settings.inputAsciimath"}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormButtons}
</form>
