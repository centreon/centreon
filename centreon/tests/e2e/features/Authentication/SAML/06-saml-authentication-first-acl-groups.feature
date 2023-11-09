@REQ_MON-22149
Feature: SAML authentication
    As an admin of Centreon Platform
    I want to be able to make use of an external authentication provider
    So that Platform users can use existing authentication services to authenticate

  @TEST_MON-22155
  Scenario: Assign users from 3rd party authentication service to first ACL group
    Given an administrator is logged on the platform
    When the administrator sets valid settings in the Roles mapping and activate apply first only and saves
    Then the users from the 3rd party authentication service are attached to ACL group on the first condition validated by order defined in the UI