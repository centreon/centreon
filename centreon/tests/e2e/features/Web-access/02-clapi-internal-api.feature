Feature: CLAPI through the internal API
  As a Centreon administrator
  I want the CLAPI actions sent through the internal API of the web interface to be accepted from a super administrator only
  So that a user session cannot change objects, such as the admin rights of an account

  @MON-215121
  Scenario Outline: CLAPI action through the internal API as <persona>
    Given a contact without admin rights exists
    # CLAPI through the internal API is an admin-only feature: editor and operator are checked as denied profiles
    Given the <persona> user is logged in
    When the user runs a CLAPI action through the internal API
    Then the action is <outcome>
    And the contact's admin rights are <contact_state>

    Examples:
      | persona            | outcome  | contact_state |
      | super_admin        | accepted | changed       |
      | user_administrator | refused  | unchanged     |
      | user_editor        | refused  | unchanged     |
      | user_operator      | refused  | unchanged     |
      | user_unprivileged  | refused  | unchanged     |
