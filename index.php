<?php

/**
 * @defgroup plugins_importexport_crossrefConference CrossRefConference Plugin
 */
 
/**
 * @file plugins/importexport/crossrefConference/index.php
 *
 * @ingroup plugins_importexport_crossrefConference
 * @brief Wrapper for CrossRefConference export plugin.
 *
 */

require_once('CrossrefConferenceExportPlugin.inc.php');

return new CrossrefConferenceExportPlugin();


