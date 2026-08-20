Feature: VendorConfiguration
  As a Centreon user
  I want to manipulate a vendor
  To see if all simple manipulations work

  Background:
    Given a user is logged in Centreon

  @MON-200041
  Scenario: The vendors listing loads through the AJAX framework
    Given a vendor is configured through the API
    When the user goes to "Configuration > SNMP Traps > Manufacturer"
    Then the AJAX listing table is displayed with the configured vendor

  @MON-200041
  Scenario: The search filters the vendors by name
    Given two vendors are configured through the API
    When the user goes to "Configuration > SNMP Traps > Manufacturer"
    And the user searches for the first vendor
    Then only the matching vendor is displayed

  @MON-159077
  Scenario: Create a new vendor
    When the user goes to "Configuration > SNMP Traps > Manufacturer"
    And the user adds a new vendor
    Then the vendor configuration is added to the listing page

  @MON-159078
  Scenario: Change the properties of a vendor
    Given a vendor "update" is configured
    When the user changes the properties of the vendor
    Then the properties are updated

  @MON-159079
  Scenario: Duplicate one existing vendor
    Given a vendor "duplicate" is configured
    When the user duplicates the vendor
    Then the new duplicated vendor has the same properties

  @MON-159080
  Scenario: Delete one existing vendor
    Given a vendor "delete" is configured
    When the user deletes the vendor
    Then the deleted object is not displayed in the list

  @MON-159081
  Scenario: Associate an existing vendor with an existing SNMP Trap and passive service
    Given a vendor "update" is configured
    And an SNMP Trap is linked to the vendor
    And a passive service is linked to the vendor
    When the user goes to "Configuration > SNMP Traps > Generate"
    And the user applies the trap configuration on the central poller
    Then the generation console reports a successful run
