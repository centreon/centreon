@REQ_MON-22122
Feature: OpenID Connect authentication
    As an admin of Centreon Platform
    I want to be able to use an external authentication provider
    So that Platform users can use existing authentication services to authenticate

  @TEST_MON-22131
  Scenario: Import users from 3rd party authentication service upon their first login
    Given an administrator is logged in the platform
    When the administrator activates the auto-import option for OpenID Connect
    Then the users from the 3rd party authentication service with the contact template are imported