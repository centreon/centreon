Feature: AWIE configuration export and import
  As a Centreon administrator
  I want to export the platform configuration and reach the import page
  So that I can back up and restore objects through AWIE

  Background:
    Given a super administrator is logged in

  Scenario: contacts are exported as a downloadable archive
    When the user exports the contacts from the AWIE export page
    Then an export archive is generated

  Scenario: the import page is ready to receive an archive
    When the user opens the AWIE import page
    Then the import form accepts a zip archive
