@defaultCommandTimeout(10000)
Feature: OpenID Connect authentication
    As an admin of Centreon Platform
    I want to be able to make use of an external authentication provider
    So that Platform users can use existing authentication services to authenticate

Scenario: Define a list of clients who can access the Centreon interface
    Given an administrator is logged on the platform
    When the adminstrator sets valid settings in the Authentication conditions and saves
    Then only users with the valid authentication conditions can access the platform