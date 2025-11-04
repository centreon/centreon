Feature: Host and Host Template Macros Configuration
  As a Centreon administrator
  I want to manage host macros
  So that I can verify all basic operations work correctly

  Background:
    Given a non-admin user is logged into the Centreon server
    And the non-admin user is on the "Configuration > Hosts" page

  @TEST_MON-191562
  Scenario: Create a host with macros
    When the non-admin user clicks to add a new host
    And the non-admin user fills in all mandatory fields
    And the non-admin user adds one normal macro and one password macro
    And the non-admin user clicks the "Save" button
    Then all the properties, including the macros, are successfully saved
    When the export configuration is done with success
    Then the macros are exported to the file "/var/cache/centreon/config/engine/1/hosts.cfg"

  @TEST_MON-191563
  Scenario: Update macros on an existing host
    Given an existing host with macros
    When the non-admin user opens the host for editing
    And the non-admin user updates the values of the existing macros
    And the non-admin user clicks the "Save" button
    Then the modified macros are saved successfully
    When the export configuration is done with success
    Then the macros are exported to the file "/var/cache/centreon/config/engine/1/hosts.cfg"

  @TEST_MON-191564
  Scenario: Delete macros from an existing host
    Given a configured host with macros
    When the non-admin user deletes the macros of the configured host
    And the non-admin user clicks the "Save" button
    Then the macros are deleted successfully
    When the export configuration is done with success
    Then the macros are removed from the file "/var/cache/centreon/config/engine/1/hosts.cfg"

  @TEST_MON-191565
  Scenario: Export inherited macros from a Host Template
    Given a non-admin user is on the "Configuration > Hosts > Templates" page
    And a Host Template "HT-A" exists with defined normal and password macros
    And the non-admin user is on the "Configuration > Hosts" page
    And a pre-configured Host using "HT-A" as its parent template
    When the export configuration is done with success
    Then the macros should be stored in the Host Template configuration file "/var/cache/centreon/config/engine/1/hostTemplates.cfg"
    And the Host configuration file "/var/cache/centreon/config/engine/1/hosts.cfg" should not contain the inherited macros

  @TEST_MON-191566
  Scenario: Override an inherited macro value when creating a new Host Template
    Given a non-admin user is on the "Configuration > Hosts > Templates" page
    And a pre-configured Host Template "HT-A" that contains defined macros
    When the non-admin user creates a new Host Template "HT-B" with "HT-A" as its parent
    And the non-admin user changes the value of the normal macro inherited from Host Template "HT-A"
    And the non-admin user clicks the "Save" button
    Then the normal macro value in "HT-B" should be the modified value
    And the normal macro should not be highlighted in orange
    And the password macro should still be highlighted in orange
    And the export configuration is done with success

  @TEST_MON-191567
  Scenario: Override an inherited macro value when creating a new Host
    Given a non-admin user is on the "Configuration > Hosts > Templates" page
    And a pre-configured Host Template "HT-A" that contains defined macros
    When the user creates a new Host "Host-B" using "HT-A" as its parent template
    And the non-admin user changes the value of the normal macro inherited from Host Template "HT-A"
    And the non-admin user clicks the "Save" button
    Then the normal macro value in "Host-B" should be the modified value
    And the normal macro should not be highlighted in orange
    And the password macro should still be highlighted in orange
    And the export configuration is done with success
    And the macro values in Host Template "HT-A" should remain unchanged

  @TEST_MON-191569
  Scenario: Override an inherited macro value when editing a pre-configured Host
    Given a pre-configured Host using a host template with defined macros as its parent template
    When the non-admin user opens the host for editing
    When the non-admin user changes the value of the normal macro inherited from Host Template "HT-A"
    And the non-admin user clicks the "Save" button
    Then the normal macro value in the host should be the modified value
    And the normal macro should not be highlighted in orange
    And the password macro should still be highlighted in orange
    And the macro values in Host Template "HT-A" should remain unchanged
    When the export configuration is done with success
    Then the new value of the inherited normal macro is exported to the file "/var/cache/centreon/config/engine/1/hosts.cfg"
    And  the old values of macros are exported to the host template file "/var/cache/centreon/config/engine/1/hostTemplates.cfg"