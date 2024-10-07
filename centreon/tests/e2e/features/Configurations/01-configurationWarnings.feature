Feature: Print configuration warnings
    As a Centreon user
    A user wants to know configuration issues
    So that the user can fix them

    Background:
        Given An admin user is logged in Centreon

    Scenario: Notifications enabled on service without notification period
        Given a service with notifications enabled
        And the service has no notification period
        When the configuration is exported
        Then a warning message is printed
