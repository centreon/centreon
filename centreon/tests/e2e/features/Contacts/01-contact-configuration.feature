Feature: ContactConfiguration
  As a Centreon user (admin or non-admin)
  I want to manage contacts
  To configure them

  @TEST_MON-151165
  Scenario Outline: Edit one existing contact
    Given a <userType> user is logged in a Centreon server
    And a contact is configured
    When the user updates some contact properties
    Then these properties are updated

    Examples:
      | userType   |
      | "admin"      |
      | "non-admin"  |

  @TEST_MON-151166
  Scenario Outline: Duplicate one existing contact
    Given a <userType> user is logged in a Centreon server
    And a contact is configured
    When the user duplicates the configured contact
    Then a new contact is created with identical properties

    Examples:
      | userType   |
      | "admin"      |
      | "non-admin"  |

  @TEST_MON-151167
  Scenario Outline: Delete one existing contact
    Given a <userType> user is logged in a Centreon server
    And a contact is configured
    When the user deletes the configured contact
    Then the deleted contact is not visible anymore on the contact page

    Examples:
      | userType   |
      | "admin"     |
      | "non-admin"  |

  @TEST_MON-184141
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

  @TEST_MON-184160
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

  @TEST_MON-184168
  Scenario: Check the list of contacts
    Given a non-admin user with READ ONLY rights is configured by the admin
    And a contact is configured
    When the non-admin user with READ ONLY rights is logged in a Centreon Server
    And the non-admin user with READ ONLY rights displays contacts configuration
    And the non-admin user with READ ONLY rights clicks on the configured contact
    Then the form of this contact is displayed in READ ONLY mode