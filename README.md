# Crossref Conference Export Plugin

### About
This plugin for OJS 3.2 provides an import/export plugin to generate metadata information for proceedings and paper of conference for indexing in CrossRef. Details on the XML format and data requirements is available at: [Crossref](http://www.crossref.org/schema)

### System Requirements

Same requirements as the OJS 3.x core.

### Installation  for development

To install the plugin:
1. Clone the repository
2. Change directory name for crossrefConference
3. Make a symbolic link for `plugins/importexport/`
4. Update the database to complete the plugin installation with the following command: `php tools/upgrade.php upgrade`

### Unit Tests Execution
- Before following these steps below, take a look at the following documentation: [Desenvolvendo testes de unidade para plugins PKP
](https://gitlab.lepidus.com.br/documentacao-e-tarefas/desenvolvimento_e_infra/-/wikis/Desenvolvendo-testes-de-unidade-para-plugins-PKP)

1. Clone the repository
2. Change directory name for crossrefConference
3. Copy the folder this plugin for your ojs in `plugins/importexport/`
4. Execute `lib/pkp/tools/runAllTests.sh -p`
