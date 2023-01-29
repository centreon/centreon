Feature: Web SSO authentication
    As an admin of Centreon Platform
    I want to be able to use an external authentication provider
    So platform users can use an existing authentication services to authenticate

Scenario: The third party authentication service is unavailable (Web SSO)
    Given an administrator logged in the platform
    When the administrator sets authentication mode to Web SSO only
    Then users and local admin user must not be able to authenticate