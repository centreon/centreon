Feature: OpenID Connect authentication
    As an admin of Centreon Platform
    I want to be able to make use of an external authentication provider
    So that Platform users can use existing authentication services to authenticate

Scenario: Configure an authentication provider
    Given an administrator is logged in the platform
    When the administrator sets valid settings in the OpenID Connect configuration form and saves
    Then the configuration is saved and secrets are not visible