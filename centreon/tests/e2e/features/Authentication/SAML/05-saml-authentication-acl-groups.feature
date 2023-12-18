@REQ_MON-22149
Feature: SAML authentication
    As an admin of Centreon Platform
    I want to be able to make use of an external authentication provider
    So that Platform users can use existing authentication services to authenticate

  @TEST_MON-22153
  Scenario: Assign users from 3rd party authentication service to ACL groups
    Given an administrator is logged on the platform
    When the administrator sets valid settings in the Roles mapping and saves
    Then the users from the 3rd party authentication service are attached to ACL groups for each condition validated