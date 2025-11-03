Feature: service and service Template Macros Configuration
  As a Centreon administrator
  I want to manage service macros
  So that I can verify all basic operations work correctly

  Background:
    Given a non-admin user is logged into the Centreon server
    And the non-admin user is on the "Configuration > services" page

  Scenario: Create a service with macros
    When the non-admin user clicks to add a new service
    And the non-admin user fills in all mandatory fields
    And the non-admin user adds one normal macro and one password macro
    And the non-admin user clicks the "Save" button
    Then all the properties, including the macros, are successfully saved
    When the export configuration is done with success
    Then the macros are exported to the file "/var/cache/centreon/config/engine/1/services.cfg"

  Scenario: Update macros on an existing service
    Given an existing service with macros
    When the non-admin user opens the service for editing
    And the non-admin user updates the values of the existing macros
    And the non-admin user clicks the "Save" button
    Then the modified macros are saved successfully
    When the export configuration is done with success
    Then the macros are exported to the file "/var/cache/centreon/config/engine/1/services.cfg"

  Scenario: Delete macros from an existing service
    Given a configured service with macros
    When the non-admin user deletes the macros of the configured service
    And the non-admin user clicks the "Save" button
    Then the macros are deleted successfully
    When the export configuration is done with success
    Then the macros are removed from the file "/var/cache/centreon/config/engine/1/services.cfg"

  Scenario: Export inherited macros from a Service Template
    Given a non-admin user is on the "Configuration > services > Templates" page
    And a service Template "ST-A" exists with defined normal and password macros
    And the non-admin user is on the "Configuration > services" page
    And a pre-configured service using "ST-A" as its parent template
    When the export configuration is done with success
    Then the macros should be stored in the service Template configuration file "/var/cache/centreon/config/engine/1/serviceTemplates.cfg"
    And the service configuration file "/var/cache/centreon/config/engine/1/services.cfg" should not contain the inherited macros
