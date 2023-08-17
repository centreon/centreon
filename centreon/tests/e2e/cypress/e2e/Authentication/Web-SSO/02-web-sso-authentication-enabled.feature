Feature: Web SSO authentication
    As an admin of Centreon Platform
    I want to be able to use an external authentication provider
    So the platform users can use an existing authentication services to authenticate

@ignore-scenario
Scenario: User login using 3rd party authentication service (Web SSO)
    Given an administrator logged in the platform
    When the administrator activates the Web SSO authentication on the platform
    Then any user can authenticate using the 3rd party authentication service