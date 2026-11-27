Feature: SNMP trap reception
  As an administrator
  I want the values received in an SNMP trap to be written as data
  So that a trap sent by anyone on the network cannot run a command on the poller

  Background:
    Given a passive service "qa-trap-svc" on a host monitored by a poller
    And a trap definition linked to "qa-trap-svc" with the output "$1" and "Submit result" enabled
    And the trap database is generated and exported and centreontrapd is restarted on the poller

  @MON-216112 @system
  Scenario: A normal varbind updates the passive service
    When a trap with the varbind "normal value" is sent to the poller
    Then the output of "qa-trap-svc" is "normal value"

  @MON-216112 @system
  Scenario Outline: Shell characters in a varbind are written as data and nothing is run
    When a trap with the varbind "<varbind>" is sent to the poller
    Then the output of "qa-trap-svc" is "<shown>"
    And no file "/var/spool/centreon/qa-214700-<n>" exists on the poller

    # Two transformations predate the fix and are kept: the trap parser turns a backtick into a single quote,
    # and the Engine shows ";" as ":" in a passive output.
    Examples:
      | n | varbind                                    | shown                                      |
      | 1 | $(id > /var/spool/centreon/qa-214700-1)    | $(id > /var/spool/centreon/qa-214700-1)    |
      | 2 | `id > /var/spool/centreon/qa-214700-2`     | 'id > /var/spool/centreon/qa-214700-2'     |
      | 3 | x; id > /var/spool/centreon/qa-214700-3    | x: id > /var/spool/centreon/qa-214700-3    |
      | 4 | x" && id > /var/spool/centreon/qa-214700-4 | x" && id > /var/spool/centreon/qa-214700-4 |

  @MON-216112 @system
  Scenario: A line break in a varbind does not split the external command
    When a trap with a varbind holding "line one", a line break and "PROCESS_SERVICE_CHECK_RESULT;qa;qa;2;injected" is sent to the poller
    Then the output of "qa-trap-svc" holds both parts on one line
    And no other service gets the result "injected"

  @MON-216112 @system
  Scenario: A trap with two varbinds and the output "$*" keeps its output
    Given the output of the trap definition is "$*"
    When a trap with the varbinds "first" and "second" is sent to the poller
    Then the output of "qa-trap-svc" is the same as before the fix

  @MON-216112 @system
  Scenario: A special command calling a script receives the varbind as one argument
    Given the special command of the trap definition is "/usr/local/bin/qa-trap-214700.sh "$1"" and the script writes its arguments to a file
    When a trap with the varbind "$(id > /var/spool/centreon/qa-214700-5)" is sent to the poller
    Then the script has received "$(id > /var/spool/centreon/qa-214700-5)" as its only argument
    And no file "/var/spool/centreon/qa-214700-5" exists on the poller
