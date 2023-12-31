<script type="text/javascript">
    $(function() {ldelim}
        $('#conferenceDataForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
    {rdelim});
</script>
<form class="pkp_form" id="conferenceDataForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" component='grid.settings.plugins.settingsPluginGridHandler' plugin="CrossrefConferenceExportPlugin" category="importexport" verb="settings" save=true}">
    {csrf}
    {fbvFormArea id="conferenceDataFormArea"}
        <h4 class="pkp_help">{translate key="plugins.importexport.crossrefConference.settings.conferenceName"}</h4>
        {fbvFormSection}
            {fbvElement type="text" id="conferenceName" value=$conferenceName|escape required="true" label="plugins.importexport.crossrefConference.settings.form.conferenceName" maxlength="255" size=$fbvStyles.size.MEDIUM}
        {/fbvFormSection}
    {/fbvFormArea}
    {fbvFormButtons submitText="common.save"}
    <p><span class="formRequired">{translate key="common.requiredField"}</span></p>
</form>
