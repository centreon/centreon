@REQ_MON-22149
Feature: SAML authentication
  As an admin of Centreon Platform
  I want to be able to make use of an external authentication provider
  So that Platform users can use existing authentication services to authenticate

  Background:
    Given an administrator is logged on the platform

  @TEST_MON-22125
  Scenario: Configure an authentication provider
    When the administrator sets valid settings in the SAML configuration form and saves
    Then the configuration is saved

  @TEST_MON-22123
  Scenario: Default authentication mode Mixed
    When the administrator first configures the authentication mode
    Then default authentication mode must be Mixed and users created locally to centreon platform must be able to authenticate

  @TEST_MON-22152
  Scenario: Enable SAML authentication
    When the administrator activates SAML authentication on the platform
    Then any user can authenticate using the authentication provider that is configured