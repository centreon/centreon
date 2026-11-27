# TODO: add @REQ_MON-<id> once the functional requirement for recurrent downtimes is identified
Feature: Recurrent downtime actions restricted by resource access
  As a Centreon administrator
  I want a restricted user to manage only the recurrent downtimes whose resources they can access
  So that nobody changes a downtime set on resources outside their access

  # Personas: the rule only applies to non-admin users, whatever their role, so a single restricted
  # user (non-admin, read/write access to the page, one host granted) covers it. The super admin
  # bypasses the rule and keeps every action.

  Background:
    Given an administrator is logged in
    And a restricted user has access to one host only
    And a recurrent downtime exists on the host within the restricted user's access
    And a recurrent downtime exists on a host outside the restricted user's access

  @MON-213799
  Scenario Outline: A restricted user can <action> a recurrent downtime within their access
    Given the recurrent downtimes are <initial_status>
    When the restricted user applies the "<action>" action to the recurrent downtime within their access
    Then the recurrent downtime within their access is <outcome>
    And no refusal message is displayed

    Examples:
      | action  | initial_status | outcome  |
      | enable  | disabled       | enabled  |
      | disable | enabled        | disabled |

  @MON-213799
  Scenario Outline: A restricted user cannot <action> a recurrent downtime outside their access
    Given the recurrent downtimes are <initial_status>
    When the restricted user applies the "<action>" action to the recurrent downtime outside their access
    Then a refusal message is displayed
    And the recurrent downtime outside their access is still <initial_status> and not duplicated

    Examples:
      | action  | initial_status |
      | enable  | disabled       |
      | disable | enabled        |

  @MON-213799
  Scenario Outline: A mass <action> by a restricted user only processes the recurrent downtimes within their access
    Given the recurrent downtimes are <initial_status>
    When the restricted user applies the "<action>" mass action to both recurrent downtimes
    Then the recurrent downtime within their access is <outcome>
    And a refusal message is displayed
    And the recurrent downtime outside their access is still <initial_status> and not duplicated

    Examples:
      | action    | initial_status | outcome    |
      | enable    | disabled       | enabled    |
      | disable   | enabled        | disabled   |
      | duplicate | enabled        | duplicated |
      | delete    | enabled        | deleted    |

  @MON-213799
  Scenario: The super admin can act on every recurrent downtime
    When the super admin applies the "delete" mass action to both recurrent downtimes
    Then both recurrent downtimes are deleted
    And no refusal message is displayed
