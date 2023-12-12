@REQ_MON-22149
Feature: SAML authentication
    As an admin of Centreon Platform
    I want to be able to make use of an external authentication provider
    So that Platform users can use existing authentication services to authenticate

  @TEST_MON-22154
  Scenario: Authorize access to Centreon application
    Given an administrator is logged on the platform
    When the administrator sets valid settings in the authentication conditions and saves
    Then the users can access to Centreon UI only if all conditions are met