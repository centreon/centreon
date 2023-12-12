@REQ_MON-22149
Feature: SAML authentication
  As an admin of Centreon Platform
  I want to be able to make use of an external authentication provider
  So that Platform users can use existing authentication services to authenticate

  @TEST_MON-22151
  Scenario: Assign users from 3rd party authentication service to contact groups
    Given an administrator is logged on the platform
    When the administrator sets valid settings in the Groups mapping and saves
    Then the users from the 3rd party authentication service are attached to contact groups for each condition validated