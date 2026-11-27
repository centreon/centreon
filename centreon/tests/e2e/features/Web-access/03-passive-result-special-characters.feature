Feature: Special characters in a submitted passive result
  As a Centreon administrator
  I want the text of a submitted passive result to be handled as data whoever submits it
  So that a line break or a configuration snippet in a result never changes the monitoring configuration or runs a command

  @MON-215164 @system
  Scenario Outline: Special characters in a submitted passive result are handled as data for <persona>
    Given a host monitored by the central poller
    Given the <persona> user is logged in
    When the user submits a passive result with line breaks, braces and a command definition in the service name
    And the user submits a passive result whose performance data holds a line break followed by an external command
    And an administrator exports and reloads the configuration of the central poller
    Then the submissions are refused or the text is kept as plain data
    And the configuration export ends with no error
    And no injected service or command appears in the generated configuration
    And no injected external command is executed
    And a normal passive result is still shown on the resource in Resources Status

    Examples:
      | persona            |
      | super_admin        |
      | user_administrator |
      | user_editor        |
      | user_operator      |
      | user_unprivileged  |
