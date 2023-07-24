# Crossref Conference Export Plugin

This plugin for OJS 3.3 provides an import/export plugin to generate metadata information for proceedings and paper of conference for indexing in CrossRef. Details on the XML format and data requirements is available at: [Crossref](http://www.crossref.org/schema)

## Compatibility

The latest release of this plugin is compatible with the following PKP applications:

* OJS 3.3.0

## Installation

### Instructions for production

Enter the administration area of ​​your application and navigate to Settings> Website> Plugins> Upload a new plugin.
Under Upload file select the file crossrefConference.tar.gz.
Click Save and the plugin will be installed on your website.

### Installation for development

Update the database to complete the plugin installation with the following command: `php tools/upgrade.php upgrade`

# License

__This plugin is licensed under the GNU General Public License v3.0__

__Copyright (c) 2022 Lepidus Tecnologia__
