@REQ_MON-22149
Feature: SAML authentication
  As an admin of Centreon Platform
  I want to be able to make use of an external authentication provider
  So that Platform users can use existing authentication services to authenticate

  @TEST_MON-22150
  Scenario: SAML Authentication mode
    Given an administrator is logged on the platform
    When the administrator sets authentication mode to SAML only
    Then only existing users on Centreon must be able to authenticate with only SAML protocol