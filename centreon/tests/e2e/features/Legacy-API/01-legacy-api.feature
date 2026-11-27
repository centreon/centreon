Feature: Legacy API (v1)
  As an API consumer
  I want the legacy API (v1) to authenticate me, expose real-time and metric data and apply my access rights
  So that integrations built on the API v1 keep working with the same security as the web interface

  @MON-214353
  Scenario: API v1 authentication returns a token that opens the API and refuses bad credentials and unknown tokens
    Given an administrator account and a shell with curl reaching the central
    When the administrator posts its login and password to "api/index.php?action=authenticate"
    Then the response is HTTP 200 with a non-empty "authToken"
    And a real-time hosts listing sent with this token in the "centreon-auth-token" header returns HTTP 200 and a JSON array
    When the authenticate action receives a wrong password
    Then the response is HTTP 401 "Authentication failed"
    When the authenticate action receives no password
    Then the response is HTTP 400 "Bad parameters"
    When the listing is sent without the "centreon-auth-token" header
    Then the response is HTTP 403 "Unauthorized"
    When the listing is sent with an unknown token
    Then the response is HTTP 403 "Unauthorized" and lists no host

  @MON-214354
  Scenario: A service result submitted through API v1 is listed by the real-time services endpoint with its status, output and perfdata
    Given an administrator token from the API v1 authenticate action
    And a host "rbt-api-svc-host" with a passive service "rbt-api-passive" created through the API v1 CLAPI action and exported with APPLYCFG
    When a critical result with output "rbt critical" and perfdata "rbt_value=95;80;90" is submitted for the service through API v1
    Then the real-time services listing searched on "rbt-api-svc-host" returns "rbt-api-passive" with state 2, output "rbt critical" and that perfdata
    And the listing filtered on status "critical" contains the service and the listing filtered on status "ok" does not
    And a result for an unknown service is answered with code 404 "The service is not present."

  @MON-214355
  Scenario: The API v1 metric endpoint returns the performance data series of a monitored service
    Given an administrator token from the API v1 authenticate action
    And a host "rbt-api-metric-host" with the service "rbt-api-ping" from the template "Ping-LAN" checked every minute, created through the API v1 CLAPI action and exported
    And the service has been checked for 5 minutes
    When the metric data of the service over the last hour is requested from "centreon_metric" with the action "metricsDataByService"
    Then the response is HTTP 200 with one entry for "<host id>_<service id>"
    And each curve has a label and as many values as there are timestamps, with at least one numeric value
    When the start and end parameters are missing
    Then the response is HTTP 400 "Bad parameters"
    When an unknown service is requested
    Then the response is HTTP 404 "Graph not found"

  @MON-214356
  Scenario: API v1 refuses a user without API access and limits a real-time API user to the resources of its access groups
    Given an administrator token from the API v1 authenticate action
    And hosts "rbt-api-acl-in" and "rbt-api-acl-out" with a "Ping-LAN" service each, created through the API v1 CLAPI action and exported
    And a non-admin user "rbt-api-rt-user" with only "Reach API Realtime", member of an access group granted "rbt-api-acl-in" only
    And a non-admin user "rbt-api-no-access" without any API access
    When "rbt-api-no-access" authenticates on API v1
    Then the response is HTTP 403 "Unauthorized"
    When "rbt-api-rt-user" authenticates on API v1
    Then the response is HTTP 200 with an authToken
    And with this token a CLAPI action is refused with HTTP 403 "Forbidden"
    And the real-time hosts listing searched on "rbt-api-acl" returns "rbt-api-acl-in" only while the administrator gets both hosts
    And the real-time services listing returns only the service of "rbt-api-acl-in"
    And the metric data of the service of "rbt-api-acl-out" is refused with HTTP 403 "Access denied"
