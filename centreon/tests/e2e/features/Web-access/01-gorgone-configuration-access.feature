Feature: Gorgone configuration access
  As a Centreon administrator
  I want the Gorgone configuration of a poller to be readable only by the users holding the poller configuration right
  So that the internal service details it holds are not exposed to the other users

  @MON-215117
  Scenario Outline: Gorgone configuration access depends on the poller configuration right for <persona>
    Given a poller is configured on the platform
    Given the <persona> user is logged in
    When the user requests the Gorgone configuration of the poller
    Then the Gorgone configuration is <outcome>

    Examples:
      | persona            | outcome  |
      | super_admin        | returned |
      | user_administrator | returned |
      | user_editor        | refused  |
      | user_operator      | refused  |
      | user_unprivileged  | refused  |
