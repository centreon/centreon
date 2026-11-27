Feature: SNMP trap processing
  As an administrator
  I want a received SNMP trap to update the service it is linked to
  So that the alarms sent by the monitored equipment are shown in Centreon

  @MON-214337 @system
  Scenario: A received SNMP trap sets the status and output of its passive service
    Given an admin user is logged in Centreon
    And the SNMP trap "rbt-trap" has an advanced matching rule
    And snmptrapd and centreontrapd are running on the Central
    When the user links the trap "rbt-trap" to the passive service "rbt-trap-service" of the host "rbt-trap-host"
    And the user exports the configuration of the Central
    And the user generates the trap database of the Central with "Apply configurations" and the "Reload" signal
    And a trap "rbt-trap" is sent with the value "up"
    Then the service "rbt-trap-service" is "Warning" with the output "rbt-trap up" in Resources status
    When a trap "rbt-trap" is sent with the value "down"
    Then the service "rbt-trap-service" is "Critical" with the output "rbt-trap down" in Resources status

  # @ignore: the poller of the CI stack ("poller" profile, centreon-poller-alma9 image built in
  # centreon-collect) ships neither snmptrapd nor centreontrapd and registers itself under its
  # container hostname, so no poller "rbt-status-poller" can receive and process a trap there.
  @MON-214338 @system @ignore
  Scenario: A poller processes a received trap with the trap database exported from the central
    Given an admin user is logged in Centreon
    And the SNMP trap "rbt-trap" has an advanced matching rule
    And a running poller "rbt-status-poller" with snmptrapd and centreontrapd running
    When the user links the trap "rbt-trap" to the passive service "rbt-trap-service" of the host "rbt-trap-poller-host" monitored by "rbt-status-poller"
    And the user exports the configuration of "rbt-status-poller"
    And the user generates the trap database of "rbt-status-poller" with "Apply configurations" and the "Reload" signal
    Then the trap database on "rbt-status-poller" contains the trap "rbt-trap"
    When a trap "rbt-trap" is sent to "rbt-status-poller" with the value "down"
    Then the service "rbt-trap-service" of "rbt-trap-poller-host" is "Critical" in Resources status

  @MON-215225 @system
  Scenario: A received SNMP trap resolves host macros in its PREEXEC and special command
    Given an administrator is logged in
    And a host with the custom macro "HOSTNAME" set to "macro-value" is monitored by the Central
    And a passive service of this host is linked to a trap whose PREEXEC command and special command use the host macro "$_HOSTHOSTNAME$"
    And the SNMP trap configuration is generated and applied on the Central
    When the Central receives an SNMP trap matching this trap definition from the host
    Then the PREEXEC command output in the centreontrapd log contains "macro-value"
    And the special command output in the centreontrapd log contains "macro-value"
    And the centreontrapd log does not contain the literal macro "$_HOSTHOSTNAME$" in any executed command

  # @ignore: Centreon DSM (dsmd, dsmclient) is not installed in the centreon-web image used by
  # the CI stack, and the centreon-dsm module has no E2E image of its own.
  @MON-214373 @system @ignore
  Scenario: A trap routed to a DSM pool fills a free slot and its closing trap frees it
    Given an admin user is logged in Centreon
    And snmptrapd, centreontrapd and dsmd are running on the Central
    And the SNMP trap "rbt-dsm-trap" runs the DSM client as special command and is linked to the active service "rbt-dsm-link" of the host "rbt-dsm-host"
    And a pool "rbt-dsm-pool" of 3 slots "rbt-slot-" exists on the host "rbt-dsm-host"
    And the configuration and the trap database of the Central are generated and applied
    When a trap "rbt-dsm-trap" opens the alarm "rbt-a1"
    Then a free slot of "rbt-dsm-host" turns Critical with the trap's output and keeps the alarm id
    When a trap "rbt-dsm-trap" opens the alarm "rbt-a2"
    Then another free slot turns Critical and the first slot is unchanged
    When a trap "rbt-dsm-trap" closes the alarm "rbt-a1"
    Then the slot of "rbt-a1" turns OK and the slot of "rbt-a2" stays Critical
