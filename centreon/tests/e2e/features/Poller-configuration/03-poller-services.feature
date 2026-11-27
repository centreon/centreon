Feature: Poller services
  As an administrator
  I want the services of my pollers to stop, restart and stay connected cleanly
  So that monitoring goes on after maintenance operations and network outages

  @MON-215168 @system
  Scenario: Stop and restart the Broker and the Engine agent services
    Given a platform is running with the Broker and the Engine agent services started
    And monitoring events are flowing through the platform
    When the administrator stops the Broker service
    And the administrator stops the Engine agent service
    Then each service has exited cleanly with no crash and no error in its log
    When the administrator starts the Broker and the Engine agent services again
    Then both services are running
    And the monitoring events are processed again

  # @ignore: the poller of the CI stack ("poller" profile, centreon-poller-alma9 image) registers
  # itself under its container hostname with a hand-written Gorgone configuration (engine module
  # only, no action module), and the central restarts gorgoned every minute while a declared poller
  # has no Broker instance: a poller "rbt-orch-poller" installed from the displayed configuration
  # and disconnected for 6 minutes cannot be checked there.
  @MON-214357 @system @ignore
  Scenario: A poller using the ZMQ protocol is connected to the central Gorgone and recovers after a restart
    Given a poller "rbt-orch-poller" declared with the Gorgone connection protocol "ZMQ (Default)"
    And the Gorgone configuration displayed for "rbt-orch-poller" is installed on the poller
    When gorgoned is restarted on the poller and then on the central
    Then the central Gorgone connection status lists "rbt-orch-poller" with a ping received less than 2 minutes ago
    And a command sent to "rbt-orch-poller" through the central Gorgone API runs on the poller
    When gorgoned is stopped on the poller for 6 minutes
    Then the central Gorgone connection status shows no ping received from "rbt-orch-poller" for more than 5 minutes
    When gorgoned is started again on the poller
    Then the central Gorgone connection status shows a ping received from "rbt-orch-poller" less than 2 minutes ago
    And a command sent to "rbt-orch-poller" through the central Gorgone API runs on the poller

  # @ignore: the PullWSS poller of the CI stack ("poller-container" profile) is created by the
  # install-poller flow in a separate Docker Compose project, under the fixed name
  # "poller-container", and its containers cannot be reached by the Cypress container commands.
  @MON-214358 @system @ignore
  Scenario: A poller using the PullWSS protocol connects to the central and reconnects after a session expiry
    Given a poller "rbt-orch-wss-poller" declared with the Gorgone connection protocol "PullWSS"
    And the Gorgone configuration displayed for "rbt-orch-wss-poller" is installed on the poller
    When gorgoned is restarted on the poller
    Then the central Gorgone connection status lists "rbt-orch-wss-poller" with a ping received less than 2 minutes ago
    And a command sent to "rbt-orch-wss-poller" through the central Gorgone API runs on the poller
    When the configuration of "rbt-orch-wss-poller" is exported with the Reload method
    Then the files generated on the central for "rbt-orch-wss-poller" are present on the poller
    When the poller stays disconnected longer than the sessions expiration time of the central
    And gorgoned is started again on the poller
    Then the central accepts "rbt-orch-wss-poller" again without restarting gorgoned on the central

  # @ignore: the CI stack has a remote server ("remote-server" profile) but no poller attached to
  # it: the only poller container ("poller" profile) registers itself to the central.
  @MON-214359 @system @ignore
  Scenario: A command and a configuration sent from the central reach a poller attached to a remote server
    Given a remote server "rbt-orch-remote" linked to the central
    And a poller "rbt-orch-rpoller" attached to "rbt-orch-remote" monitoring the host "rbt-orch-rhost"
    When the central Gorgone connection status is read
    Then "rbt-orch-rpoller" is listed among the nodes reached through "rbt-orch-remote"
    When the configuration of "rbt-orch-rpoller" is exported from the central with the Reload method
    Then the files generated on the central for "rbt-orch-rpoller" are present on the poller
    And "rbt-orch-rhost" is Up in the Resources status of the central
    When a forced check of the service "Ping" of "rbt-orch-rhost" is sent from the central
    Then the Engine of "rbt-orch-rpoller" logs the external command
    And the last check of the service is refreshed in the Resources status of the central

  # @ignore: no Centreon Monitoring Agent runs in the CI stack (no agent image or compose
  # profile), so no agent can connect with an unknown host name.
  @MON-213254 @system @ignore
  Scenario: Enable "Create hosts automatically" on an agent configuration and see an unknown agent's host created on its poller
    Given an administrator is logged in
    And a poller has a working agent connected
    # Administrator only: the agent configuration is an admin-level setting
    Given the agent configuration of the poller has "Create hosts automatically" enabled
    And the configuration of the poller is exported
    When the agent connects with a host name that does not exist in Centreon
    Then the host is created on the poller the agent is connected to
    And the host is linked to the host template announced by the agent
    And the host and its services are monitored after the next export of the poller
