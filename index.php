<?php

/**
 * @defgroup plugins_importexport_crossrefConference CrossRefConference Plugin
 */
 
/**
 * @file plugins/importexport/crossrefConference/index.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @ingroup plugins_importexport_crossrefConference
 * @brief Wrapper for CrossRefConference export plugin.
 *
 */

require_once('CrossrefConferenceExportPlugin.inc.php');

return new CrossrefConferenceExportPlugin();


