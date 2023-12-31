{**
 * plugins/importexport/crossrefConference/templates/index.tpl
 *
 * List of operations this plugin can perform
 *}


{extends file="layouts/backend.tpl"}

{block name="page"}
	{if !empty($configurationErrors) ||
		!$currentContext->getData('publisherInstitution')|escape ||
		!$exportArticles ||
		(!$currentContext->getData('onlineIssn') && !$currentContext->getData('printIssn'))}
		{assign var="allowExport" value=false}
	{else}
		{assign var="allowExport" value=true}
	{/if}

	<script type="text/javascript">
		$(function() {ldelim}
			$('#importExportTabs').pkpHandler('$.pkp.controllers.TabHandler');
		{rdelim});
	</script>
	<div id="importExportTabs">
		<ul>
			<li><a href="#settings-tab">{translate key="plugins.importexport.common.settings"}</a></li>
			{if $allowExport}
				<li><a href="#conferenceData-tab">{translate key="plugins.importexport.crossrefConference.settings.conferenceData"}</a></li>
				<li><a href="#exportSubmissions-tab">{translate key="plugins.importexport.common.export.articles"}</a></li>
			{/if}
		</ul>
		<div id="settings-tab">
			{if !$allowExport}
				<div class="pkp_notification" id="crossrefConfigurationErrors">
					{foreach from=$configurationErrors item=configurationError}
						{if $configurationError == $smarty.const.DOI_EXPORT_CONFIG_ERROR_DOIPREFIX}
							{include file="controllers/notification/inPlaceNotificationContent.tpl" notificationId=crossrefConfigurationErrors notificationStyleClass="notifyWarning" notificationTitle="plugins.importexport.common.missingRequirements"|translate notificationContents="plugins.importexport.common.error.DOIsNotAvailable"|translate}
						{elseif $configurationError == $smarty.const.EXPORT_CONFIG_ERROR_SETTINGS}
							{include file="controllers/notification/inPlaceNotificationContent.tpl" notificationId=crossrefConfigurationErrors notificationStyleClass="notifyWarning" notificationTitle="plugins.importexport.common.missingRequirements"|translate notificationContents="plugins.importexport.common.error.pluginNotConfigured"|translate}
						{/if}
					{/foreach}
					{if !$currentContext->getData('publisherInstitution')}
						{capture assign=journalSettingsUrl}{url router=$smarty.const.ROUTE_PAGE page="management" op="settings" path="context" escape=false}{/capture}
						{capture assign=missingPublisherMessage}{translate key="plugins.importexport.crossrefConference.error.publisherNotConfigured" journalSettingsUrl=$journalSettingsUrl}{/capture}
						{include file="controllers/notification/inPlaceNotificationContent.tpl" notificationId=crossrefConfigurationErrors notificationStyleClass="notifyWarning" notificationTitle="plugins.importexport.common.missingRequirements"|translate notificationContents=$missingPublisherMessage}
					{/if}
					{if !$currentContext->getData('onlineIssn') && !$currentContext->getData('printIssn')}
						{capture assign=journalSettingsUrl}{url router=$smarty.const.ROUTE_PAGE page="management" op="settings" path="context" escape=false}{/capture}
						{capture assign=missingIssnMessage}{translate key="plugins.importexport.crossrefConference.error.issnNotConfigured" journalSettingsUrl=$journalSettingsUrl}{/capture}
						{include file="controllers/notification/inPlaceNotificationContent.tpl" notificationId=crossrefConfigurationErrors notificationStyleClass="notifyWarning" notificationTitle="plugins.importexport.common.missingRequirements"|translate notificationContents=$missingIssnMessage}
					{/if}
					{if !$exportArticles}
						{include file="controllers/notification/inPlaceNotificationContent.tpl" notificationId=crossrefConfigurationErrors notificationStyleClass="notifyWarning" notificationTitle="plugins.importexport.common.missingRequirements"|translate notificationContents="plugins.importexport.crossrefConference.error.noDOIContentObjects"|translate}
					{/if}
				</div>
			{/if}

			{capture assign=crossrefSettingsGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.settings.plugins.settingsPluginGridHandler" op="manage" plugin="CrossrefConferenceExportPlugin" category="importexport" verb="index" escape=false}{/capture}
			{load_url_in_div id="crossrefSettingsGridContainer" url=$crossrefSettingsGridUrl}
		</div>

		{if $allowExport}
			<div id="exportSubmissions-tab">
				<script type="text/javascript">
					$(function() {ldelim}
						// Attach the form handler.
						$('#exportSubmissionXmlForm').pkpHandler('$.pkp.controllers.form.FormHandler');
					{rdelim});
				</script>
				<form id="exportSubmissionXmlForm" class="pkp_form" action="{plugin_url path="exportSubmissions"}" method="post">
					{csrf}
					<input type="hidden" name="tab" value="exportSubmissions-tab" />
					{fbvFormArea id="submissionsXmlForm"}
						{capture assign=submissionsListGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.pubIds.PubIdExportSubmissionsListGridHandler" op="fetchGrid" plugin="crossrefConference" category="importexport" escape=false}{/capture}
						{load_url_in_div id="submissionsListGridContainer" url=$submissionsListGridUrl}
						{fbvFormSection list="true"}
							{fbvElement type="checkbox" id="validation" label="plugins.importexport.crossrefConference.settings.form.validation" checked=$validation|default:false}
							{fbvElement type="checkbox" id="onlyValidateExport" label="plugins.importexport.crossrefConference.settings.form.onlyValidateExport" checked=$onlyValidateExport|default:false}
						{/fbvFormSection}
						{if !empty($actionNames)}
							{fbvFormSection}
							<ul class="export_actions">
								{foreach from=$actionNames key=action item=actionName}
									<li class="export_action">
										{fbvElement type="submit" label="$actionName" id="$action" name="$action" value="1" class="$action" translate=false inline=true}
									</li>
								{/foreach}
							</ul>
							{/fbvFormSection}
						{/if}
					{/fbvFormArea}
				</form>
				<p>{translate key="plugins.importexport.crossrefConference.statusLegend"}</p>
			</div>

			<div id="conferenceData-tab">
				{include file="../../../plugins/importexport/crossrefConference/templates/conferenceDataForm.tpl"}
			<div>
		{/if}
	</div>
{/block}
