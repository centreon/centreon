@defaultCommandTimeout(10000)
Feature: OpenID Connect authentication
    As an admin of Centreon Platform
    I want to be able to make use of an external authentication provider
    So that Platform users can use existing authentication services to authenticate

Scenario: Assign users from 3rd party authentication service to contact groups
    Given an administrator is logged in the platform
    When the administrator sets valid settings in the Groups mapping and saves
    Then the users from the 3rd party authentication service are affected to contact groups