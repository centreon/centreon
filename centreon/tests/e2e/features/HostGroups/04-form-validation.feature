Feature: Configuration form validation
  As a Centreon administrator
  I want a configuration form to check its fields, protect my unsaved changes, save and report the API errors
  So that I neither lose my input nor save an invalid object

  @MON-214343
  Scenario: A configuration form validates its fields, guards unsaved changes, saves and reports API errors
    Given an admin user is logged in a Centreon server
    And no host group named "rbt-ui-form-hg" exists
    When the user opens the host group creation form and types an alias without a name
    Then the "Name" field shows "Required" and "Save" is disabled
    When the user cancels the form
    Then the dialog "Do you want to leave this page?" lets the user stay on the form
    When the user types the name "rbt-ui-form-hg", cancels the form and discards the changes
    Then no host group "rbt-ui-form-hg" is created
    When the user saves the host group "rbt-ui-form-hg" with the alias "rbt ui form"
    Then the message "Host group created" is displayed and the host group is listed
    When the user reopens its edit form from the URL and changes the alias to "rbt ui form edited"
    Then the message "Host group updated" is displayed and the alias is stored
    When the user saves another host group named "rbt-ui-form-hg"
    Then an error message says that the name already exists and the form stays open
