Feature: Access control of the monitoring API (v2)
  As a Centreon administrator
  I want the API v2 to apply the access groups of a user to every resource it reads or acts on
  So that a user cannot reach a host of another access group by guessing its id

  @MON-214330
  Scenario: A user restricted by ACL cannot read or act on a host of another access group through API v2
    Given the hosts rbt-idor-a and rbt-idor-b are each visible to a different access group
    And the non-admin user rbt-idor-user belongs only to the access group that sees rbt-idor-a
    When rbt-idor-user requests the details of rbt-idor-b by its id through API v2
    Then the response is 403 or 404 and does not contain rbt-idor-b
    When rbt-idor-user lists the monitoring resources
    Then rbt-idor-a is listed and rbt-idor-b is not
    When rbt-idor-user acknowledges rbt-idor-b by its id
    Then the call is refused and rbt-idor-b is not acknowledged
