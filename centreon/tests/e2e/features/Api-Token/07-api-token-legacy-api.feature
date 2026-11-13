@REQ_MON-213074
Feature: Legacy API token validity

  As an administrator
  I want the legacy API (v1) to refuse API tokens that are disabled or expired
  So that a token which is no longer valid cannot be used to read data or send commands

  @MON-214823
  Scenario Outline: The legacy API refuses a disabled or expired API token
    Given a non-admin user "qa-api-v1" allowed to use the API v1 with write access on host "Centreon-Server"
    And an API token "<token name>" generated for "qa-api-v1"
    And a realtime hosts listing on the API v1 with this token returns HTTP 200
    When the token is <state change>
    Then the realtime hosts listing on the API v1 with this token returns HTTP 403
    And a "SCHEDULE_FORCED_HOST_SVC_CHECKS" external command sent to the API v1 with this token returns HTTP 403
    And the external command is not forwarded to the poller
    And a call to the current API with this token returns HTTP 401

    Examples:
      | token name     | state change                                  |
      | qa-v1-disabled | disabled from the Authentication Tokens page  |
      | qa-v1-expired  | moved past its expiration date                |
