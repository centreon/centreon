@ignore
@REQ_MON-151418
Feature: Generate a service contact configuration
  As a Centreon admin
  I want to apply my service contacts and contact groups defined on the host
  To use these to replace the contacts and service contacts groups

  Background:
    Given a user is logged in Centreon

  @TEST_MON-151543
  Scenario: Configure checkbox Inherit only contacts and contacts group from host
    Given a service associated to a host is configured
    And the user is in the "Notifications" tab
    When the user checks case yes to enable Notifications
    And the case Inherit contacts is checked
    Then the field "Implied Contacts" is disabled
    And the field "Implied Contact Groups" is disabled
