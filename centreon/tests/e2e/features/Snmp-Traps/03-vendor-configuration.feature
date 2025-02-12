Feature: VendorConfiguration
  As a Centreon user
  I want to manipulate a vendor
  To see if all simple manipulations work

  Background:
    Given a user is logged in Centreon

  @TEST_MON-159077
  Scenario: Create a new vendor
    When the user goes to "Configuration > SNMP Traps > Manufacturer"
    And the user adds a new vendor
    Then the vendor configuration is added to the listing page

  @TEST_MON-159078
  Scenario: Change the properties of a vendor
    Given a vendor "update" is configured
    When the user changes the properties of the vendor
    Then the properties are updated

  @TEST_MON-159079
  Scenario: Duplicate one existing vendor
    Given a vendor "duplicate" is configured
    When the user duplicates the vendor
    Then the new duplicated vendor has the same properties

  @TEST_MON-159080
  Scenario: Delete one existing vendor
    Given a vendor "delete" is configured
    When the user deletes the vendor
    Then the deleted object is not displayed in the list

  @TEST_MON-159081
  Scenario: Associate an existing vendor with an existing SNMP Trap and passive service
    Given a vendor "update" is configured
    And an SNMP Trap is linked to the vendor
    And a passive service is linked to the vendor
    When the user goes to "Configuration > SNMP Traps > Generate"
    And the user clicks on "Generate"
    Then a message indicates that the "Database generation with success" is displayed on the page