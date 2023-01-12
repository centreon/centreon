Feature: Web SSO authentication
    As an admin of Centreon Platform
    I want to be able to make use of an external authentication provider
    So that Platform users can use existing authentication services to authenticate

Scenario: Default authentication mode Mixed (Web SSO)
    Given an administrator logged in the platform
    When the administrator first configures the authentication mode
    Then default authentication mode must be Mixed and users created locally to centreon platform must be able to authenticate