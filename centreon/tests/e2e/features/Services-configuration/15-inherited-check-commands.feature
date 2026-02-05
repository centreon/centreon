Feature: Manage inherited check commands
  As a Centreon user
  I want to manipulate services
  with inherited check commands from services templates

  Background:
    Given an admin user is logged in Centreon
    And a service template with check command is configured

  @TEST_MON-194172
  Scenario: Create a service by host using a template with inherited check command
    Given a host is configured
    When the admin adds a new service linked to the configured host
    And the admin selects the configured service template as parent
    And the admin saves the configuration
    Then the service is successfully created

  @TEST_MON-194175
  Scenario: Create a service by host group using a template with inherited check command
    Given a host group is configured
    When the admin adds a new service by host group linked to the configured host group
    And the admin selects the configured service template as parent
    And the admin saves the configuration
    Then the service by host group is successfully created