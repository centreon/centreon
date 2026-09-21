Feature: HostTemplateBasicsOperations
  As a Centreon admin
  I want to manipulate a host template
  To see if all simples manipulations work

  Background:
    Given an admin user is logged in a Centreon server
    And a host template is configured

  @MON-151104
  Scenario: Edit of a host template properties
    When the user changes the properties of the configured host template
    Then the properties are updated

  @MON-151105
  Scenario: Duplication of a host template
    When the user duplicates the configured host template
    Then a new host template is created with identical properties

  @MON-151109
  Scenario: Deletion of a host template
    When the user deletes the configured host template
    Then the deleted host template is not visible anymore on the host template page

  # Cloud: identical
  Scenario: Duplicating a host template does not multiply its service templates
    Given a host template with shared service templates is configured
    When the user duplicates that host template and its copy
    Then each copy carries exactly the service templates of its source

  # Cloud: identical
  Scenario: A duplication whose generated name is already taken creates nothing
    Given a host template with shared service templates is configured
    And a host template already carries the name the copy would take
    When the user duplicates that host template
    Then no duplicate host template row was created