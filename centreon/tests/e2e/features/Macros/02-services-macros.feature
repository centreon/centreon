Feature: service and service Template Macros Configuration
  As a Centreon administrator
  I want to manage service macros
  So that I can verify all basic operations work correctly

  Background:
    Given a non-admin user is logged into the Centreon server
    And the non-admin user is on the "Configuration > services" page

  @TEST_MON-191570
  Scenario: Create a service with macros
    When the non-admin user clicks to add a new service
    And the non-admin user fills in all mandatory fields
    And the non-admin user adds one normal macro and one password macro
    And the non-admin user clicks the "Save" button
    Then all the properties, including the macros, are successfully saved
    When the export configuration is done with success
    Then the macros are exported to the file "/var/cache/centreon/config/engine/1/services.cfg"

  @TEST_MON-191571
  Scenario: Update macros on an existing service
    Given an existing service with macros
    When the non-admin user opens the service for editing
    And the non-admin user updates the values of the existing macros
    And the non-admin user clicks the "Save" button
    Then the modified macros are saved successfully
    When the export configuration is done with success
    Then the macros are exported to the file "/var/cache/centreon/config/engine/1/services.cfg"

  @TEST_MON-191572
  Scenario: Delete macros from an existing service
    Given a configured service with macros
    When the non-admin user deletes the macros of the configured service
    And the non-admin user clicks the "Save" button
    Then the macros are deleted successfully
    When the export configuration is done with success
    Then the macros are removed from the file "/var/cache/centreon/config/engine/1/services.cfg"

  @TEST_MON-191573
  Scenario: Export inherited macros from a Service Template
    Given a non-admin user is on the "Configuration > services > Templates" page
    And a service Template "ST-A" exists with defined normal and password macros
    And the non-admin user is on the "Configuration > services" page
    And a pre-configured service using "ST-A" as its parent template
    When the export configuration is done with success
    Then the macros should be stored in the service Template configuration file "/var/cache/centreon/config/engine/1/serviceTemplates.cfg"
    And the service configuration file "/var/cache/centreon/config/engine/1/services.cfg" should not contain the inherited macros

  @TEST_MON-191574
  Scenario: Override an inherited macro value when creating a new Service Template
    Given a non-admin user is on the "Configuration > services > Templates" page
    And a pre-configured Service Template "ST-A" that contains defined macros
    When the non-admin user creates a new Service Template "ST-B" with "ST-A" as its parent
    And the non-admin user changes the value of the normal macro inherited from Service Template "ST-A"
    And the non-admin user clicks the "Save" button
    Then the normal macro value in "ST-B" should be the modified value
    And the normal macro should not be highlighted in orange
    And the password macro should still be highlighted in orange
    And the export configuration is done with success

  @TEST_MON-191575
  Scenario: Override an inherited macro value when creating a new Service
    Given a non-admin user is on the "Configuration > services > Templates" page
    And a pre-configured Service Template "ST-A" that contains defined macros
    When the user creates a new Service "Service-B" using "ST-A" as its parent template
    And the non-admin user changes the value of the normal macro inherited from Service Template "ST-A"
    And the non-admin user clicks the "Save" button
    Then the normal macro value in "Service-B" should be the modified value
    And the normal macro should not be highlighted in orange
    And the password macro should still be highlighted in orange
    And the export configuration is done with success
    And the macro values in Service Template "ST-A" should remain unchanged

  @TEST_MON-191576
  Scenario: Override an inherited macro value when editing a pre-configured Service
    Given a pre-configured Service using a service template with defined macros as its parent template
    When the non-admin user opens the service for editing
    When the non-admin user changes the value of the normal macro inherited from Service Template "ST-A"
    And the non-admin user clicks the "Save" button
    Then the normal macro value in the service should be the modified value
    And the normal macro should not be highlighted in orange
    And the password macro should still be highlighted in orange
    And the macro values in Service Template "ST-A" should remain unchanged
    When the export configuration is done with success
    Then the new value of the inherited normal macro is exported to the file "/var/cache/centreon/config/engine/1/services.cfg"
    And  the old values of macros are exported to the service template file "/var/cache/centreon/config/engine/1/serviceTemplates.cfg"