Feature: Web SSO authentication
    As an admin of Centreon Platform
    I want to be able to make use of an external authentication provider
    So that Platform users can use existing authentication services to authenticate

Scenario: User login using 3rd party authentication service (Web SSO)
    Given an administrator logged in the platform
    When the administrator activates the Web SSO authentication on the platform
    Then any user can authenticate using the 3rd party authentication service