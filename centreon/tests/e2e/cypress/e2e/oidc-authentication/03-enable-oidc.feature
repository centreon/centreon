Feature: OpenID Connect authentication
    As an admin of Centreon Platform
    I want to be able to make use of an external authentication provider
    So that Platform users can use existing authentication services to authenticate

Scenario: Enable OpenID Connect authentication
    Given an administrator logged in the platform
    When the administrator activates OpenID Connect authentication on the platform
    Then any user can authenticate using the authentication provider that is configured