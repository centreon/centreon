Feature: ContactConfiguration
  As a Centreon user (admin or non-admin)
  I want to manage contacts
  To configure them

  @MON-151165
  Scenario Outline: Edit one existing contact
    Given a <userType> user is logged in a Centreon server
    And a contact is configured
    When the user updates some contact properties
    Then these properties are updated

    Examples:
      | userType   |
      | "admin"      |
      | "non-admin"  |

  @MON-151166
  Scenario Outline: Duplicate one existing contact
    Given a <userType> user is logged in a Centreon server
    And a contact is configured
    When the user duplicates the configured contact
    Then a new contact is created with identical properties

    Examples:
      | userType   |
      | "admin"      |
      | "non-admin"  |

  @MON-151167
  Scenario Outline: Delete one existing contact
    Given a <userType> user is logged in a Centreon server
    And a contact is configured
    When the user deletes the configured contact
    Then the deleted contact is not visible anymore on the contact page

    Examples:
      | userType   |
      | "admin"     |
      | "non-admin"  |

  @MON-184141
  Scenario Outline: Missing required field during the creation of a contact
    Given a <userType> user is logged in a Centreon server
    And the contact configuration page is displayed
    When the user clicks on the contact creation button
    And he does not fill in the <field> field
    Then the user is not brought back to the contact configuration page
    And he sees an error displayed above the <field> field with a message "<message>"
    And the contact is not created

    Examples:
      | userType | field          | message           |
      | "admin"   | "Alias"  | Compulsory Alias  |
      | "admin"   | "Full Name"      | Compulsory Name   |
      | "admin"    | "Email"         | Valid Email       |
      | "non-admin"| "Alias" | Compulsory Alias  |
      | "non-admin"| "Full Name"      | Compulsory Name   |
      | "non-admin"| "Email"          | Valid Email       |

  @MON-184160
  Scenario Outline: Error during the update of a contact
    Given a <userType> user is logged in a Centreon server
    And a contact is configured
    When the <userType> user clicks on a this contact
    And the <userType> clears the contents of a mandatory field
    Then the user is not brought back to the contact configuration page
    And the <userType> sees an error displayed in the form
    And the contact is not updated

    Examples:
      | userType   |
      | "admin"     |
      | "non-admin"  |

  @MON-184168
  Scenario: Check the list of contacts
    Given a non-admin user with READ ONLY rights is configured by the admin
    And a contact is configured
    When the non-admin user with READ ONLY rights is logged in a Centreon Server
    And the non-admin user with READ ONLY rights displays contacts configuration
    And the non-admin user with READ ONLY rights clicks on the configured contact
    Then the form of this contact is displayed in READ ONLY mode

  @MON-200035
  Scenario: The contacts listing loads its rows over AJAX
    Given a "admin" user is logged in a Centreon server
    And test contacts exist
    When the user displays the contacts listing
    Then the listing table is displayed with contact rows

  @MON-200035
  Scenario: Searching filters the contacts listing
    Given a "admin" user is logged in a Centreon server
    And test contacts exist
    When the user displays the contacts listing
    And the user searches for a specific contact
    Then only the matching contact is displayed

  @MON-200035
  Scenario: Toggling a contact disables it
    Given a "admin" user is logged in a Centreon server
    And test contacts exist
    When the user displays the contacts listing
    And the user clicks the toggle to disable a contact
    Then the contact toggle switches to disabled
    And the toggle response is successful

  @MON-200035
  Scenario: A user cannot toggle their own account
    Given a "admin" user is logged in a Centreon server
    When the user displays the contacts listing
    Then the admin user toggle is disabled

  @MON-200035
  Scenario: A mass change only writes to the selected contacts
    Given a "admin" user is logged in a Centreon server
    And test contacts exist
    When the user displays the contacts listing
    And the user mass changes the address of the test contacts
    Then both test contacts carry the new address
    And the admin account keeps its own address

  @MON-200035
  Scenario: The contacts search term survives navigating away and back
    Given a "admin" user is logged in a Centreon server
    And test contacts exist
    When the user displays the contacts listing
    And the user searches for a specific contact
    And the user displays the contacts listing again
    Then the contacts search field still contains the search term
