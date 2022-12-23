@defaultCommandTimeout(20000)
Feature: OpenID Connect authentication
    As an admin of Centreon Platform
    I want to be able to make use of an external authentication provider
    So that Platform users can use existing authentication services to authenticate

Scenario: OpenID Connect Authentication mode
    Given an administrator is relogged on the platform
    When the administrator sets authentication mode to OpenID Connect only
    Then only users created using the 3rd party authentication provide must be able to authenticate and local admin user must not be able to authenticate