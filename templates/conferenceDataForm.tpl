<script type="text/javascript">
    $(function() {ldelim}
        $('#conferenceDataForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
    {rdelim});
</script>
<form class="pkp_form" id="conferenceDataForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" component='grid.settings.plugins.settingsPluginGridHandler' plugin="CrossrefConferenceExportPlugin" category="importexport" verb="conferenceData" action='save'}">
    {csrf}
    {fbvFormArea id="conferenceDataFormArea"}
    {fbvFormSection list=true required=true label="plugins.importexport.crossrefConference.settings.form.conferenceName"}
        {fbvElement type="radio" id="conferenceNameOptionIssueTitle" name="conferenceNameOption" label="plugins.importexport.crossrefConference.settings.form.conferenceNameOptionIssueTitle" value="issueTitle" checked=$conferenceNameOption == 'issueTitle'}
        {fbvElement type="radio" id="conferenceNameOptionCustom" name="conferenceNameOption" label="plugins.importexport.crossrefConference.settings.form.conferenceNameOptionCustom" value="custom" checked=$conferenceNameOption == 'custom'}
    {/fbvFormSection}
        {fbvFormSection label="plugins.importexport.crossrefConference.settings.conferenceName"}
            {fbvElement type="text" id="conferenceName" value=$conferenceName|escape label="plugins.importexport.crossrefConference.settings.form.conferenceName" maxlength="255" size=$fbvStyles.size.MEDIUM}
        {/fbvFormSection}
    {/fbvFormArea}
    {fbvFormButtons submitText="common.save"}
    <p><span class="formRequired">{translate key="common.requiredField"}</span></p>
</form>
<script type="text/javascript">
    $(function() {ldelim}
        function toggleConferenceNameField() {ldelim}
            if ($('#conferenceNameOptionCustom').is(':checked')) {ldelim}
                $('input[name="conferenceName"]').closest('.section').show();
            {rdelim} else {ldelim}
                $('input[name="conferenceName"]').closest('.section').hide();
            {rdelim}
        {rdelim}

        toggleConferenceNameField();

        $('input[name="conferenceNameOption"]').change(function() {ldelim}
            toggleConferenceNameField();
        {rdelim});
    {rdelim});
</script>