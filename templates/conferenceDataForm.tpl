<script type="text/javascript">
    $(function() {ldelim}
        // Attach the form handler.
        $('#conferenceDataForm').pkpHandler('$.pkp.controllers.form.FormHandler');
    {rdelim});
</script>
<form class="pkp_form" id="conferenceDataForm"  method="post" action="{url router=$smarty.const.ROUTE_COMPONENT component='grid.settings.plugins.settingsPluginGridHandler' op="manage" plugin="CrossrefConferenceExportPlugin" category="importexport" verb="settings"}">
    {csrf}
    {fbvFormArea id="conferenceDataFormArea"}
        <h4 class="pkp_help">{translate key="plugins.importexport.crossrefConference.settings.conferenceName"}</h4>
        {fbvFormSection}
            {fbvElement type="text" id="conferenceName" value=$conferenceName required="true" label="plugins.importexport.crossrefConference.settings.form.conferenceName" maxlength="60" size=$fbvStyles.size.MEDIUM}
        {/fbvFormSection}
    {/fbvFormArea}
    {fbvFormButtons submitText="common.save"}
    <p><span class="formRequired">{translate key="common.requiredField"}</span></p>
</form>

{* 
configurações
action="http://localhost:8000/index.php/relat/$$$call$$$/grid/settings/plugins/settings-plugin-grid/manage?plugin=CrossrefConferenceExportPlugin&category=importexport&verb=save"

dados da conferencia
action="http://localhost:8000/index.php/relat/$$$call$$$//manage?category=importexport&plugin=CrossrefConferenceExportPlugin&verb=settings&save=1" *}

{* http://localhost:8000/index.php/relat/$$$call$$$//manage?plugin=CrossrefConferenceExportPlugin&category=importexport&verb=save *}