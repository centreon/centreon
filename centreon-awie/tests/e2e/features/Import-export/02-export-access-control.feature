Feature: Access control of the AWIE configuration export
  As a Centreon administrator
  I want the configuration export to be downloadable only by a human administrator and only as a generated export file
  So that the CLAPI export of the platform cannot be read anonymously, by a restricted user, by a service account or outside its folder

  @MON-214171
  Scenario: Configuration export requires an admin session and rejects path traversal
    Given an admin user is logged in
    When the user exports the contacts from Configuration > Import/Export > Export
    Then a CLAPI export archive is downloaded
    When the export endpoint is called without a session
    Then the call is rejected with "Authentication required"
    And no archive is produced
    When the export endpoint is called without a session and with a path traversal in the file name
    Then the call is rejected with "Authentication required"
    When the export endpoint is called by a non-admin user without import/export rights
    Then the call is rejected with "Permission denied"
    When the export endpoint is called by an admin service account
    Then the call is rejected with "Permission denied for service accounts"
    When the export endpoint is called by an admin with a path traversal in the file name
    Then the call is rejected with "Invalid export file"
