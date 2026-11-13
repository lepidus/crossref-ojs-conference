# Crossref Conference Plugin

Generic plugin for OJS 3.5 that registers the DOIs of conference papers with Crossref, using the Crossref conference proceedings format (`conference` / `conference_paper`, schema 4.3.6). It acts as the journal's DOI registration agency: deposits, exports and the status of each DOI are handled on the OJS DOI page.

Details on the XML format and data requirements are available at [Crossref](https://www.crossref.org/documentation/schema-library/).

## Compatibility

The latest release of this plugin is compatible with the following PKP applications:

* OJS 3.5.0

Releases 1.x of this plugin are for OJS 3.3.

## Requirements

* DOIs enabled for articles, with a DOI prefix, in `Settings` > `Distribution` > `DOIs`.
* The journal publisher and an ISSN (online or print), in `Settings` > `Journal`.
* `api_key_secret` set under `[security]` in `config.inc.php`, to deposit directly from OJS. The Crossref password is stored encrypted with this secret, so the plugin does not save the password while it is missing. Changing the secret later makes the stored password unreadable: it then has to be typed again in the plugin settings.
* To export the conference location and dates, the issues must carry the conference data (`conferencePlaceCity`, `conferencePlaceCountry`, `conferenceDateBegin`, `conferenceDateEnd`), such as the data kept by the Conference plugin. Without them, only the conference name is exported.

## Installation

### Instructions for production

Enter the administration area of your application and navigate to `Settings` > `Website` > `Plugins` > `Upload a new plugin`.
Under `Upload file` select the file `crossrefConference.tar.gz`.
Click `Save` and the plugin will be installed on your website.

### Installation for development

Update the database to complete the plugin installation with the following command: `php lib/pkp/tools/installPluginVersion.php plugins/generic/crossrefConference/version.xml`

## Upgrading from OJS 3.3

Version 1.x was an import/export plugin, under `Tools`. Version 2.x is a generic plugin and a DOI registration agency. When version 2.x is installed:

* The settings of the old plugin (depositor, Crossref account, test mode and conference name) are carried over. The Crossref password is encrypted on the way; if `api_key_secret` is not set when the upgrade runs, the password is not carried over and has to be typed again.
* Journals that had the old plugin configured get the new plugin enabled and selected as their registration agency, unless another agency was already selected. The old automatic registration option becomes the automatic deposit option of OJS.
* The deposit status of each paper (registered, marked registered or failed, with the batch ID and the failure message) is carried over to its DOI.

After the upgrade, remove the old `Crossref Conference XML Export Plugin` from the `Import/Export Plugins` category, if it is still listed.

## Configuration

1. Enable the plugin in `Settings` > `Website` > `Plugins` > `Generic Plugins`.
2. In `Settings` > `Distribution` > `DOIs` > `Registration`, choose **Crossref (Conference)** as the registration agency and fill in:
   * **Depositor name** and **Depositor email**.
   * The **username** and **password** of the Crossref account, to deposit directly from OJS. Without them, the XML can still be exported. The password is never shown again after it is saved: leave the field empty to keep the stored one.
   * The **conference name**: either the title of the paper's issue, or a fixed name.
   * **Testing API**, to deposit to the Crossref test service.

Missing journal requirements are listed at the top of these settings.

## Usage

Deposit, export and mark as registered are done in `DOIs` > `Articles`, with the actions OJS offers for any registration agency. Each DOI shows its status and, when a deposit fails, the reason: the message returned by Crossref, the XML validation errors (an invalid XML is not sent), or that the plugin is not configured or the site runs in sandbox mode. Automatic deposit is enabled in the same DOI registration settings.

Deposits go through the OJS job queue, which OJS runs at the end of each request by default (`job_runner = On`). Until the job runs, the DOI stays as submitted. A site with the built-in runner turned off needs its scheduled tasks running, or a worker: `php lib/pkp/tools/jobs.php work`.

Only article DOIs are registered: issue DOIs are not part of the conference format used by this plugin.

## Known limitations

* The command line export/registration of version 1.x is no longer available: OJS 3.5 does not support it for DOI plugins.
* The "only validate export" option of version 1.x was removed. The export on the DOI page reports XML validation errors instead of downloading an invalid file.

# License

__This plugin is licensed under the GNU General Public License v3.0__

__Copyright (c) 2022-2026 Lepidus Tecnologia__
