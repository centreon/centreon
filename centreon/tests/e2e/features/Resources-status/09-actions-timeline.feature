Feature: Resources Status actions and timeline
  As a Centreon operator
  I want the actions I run from Resources Status to be applied by Engine and kept in the timeline of the resource
  So that the handling of an alert is effective and traced

  @MON-214310
  Scenario: Acknowledgement, downtime, comment and forced check on a resource are applied by Engine and listed in its timeline
    Given a host rbt-action-host with an active ping check and its passive service rbt-passive-svc are monitored
    And rbt-passive-svc is Critical after a submitted status
    When the user acknowledges rbt-passive-svc from Resources Status with a comment
    Then the service is marked as acknowledged in the listing and the acknowledgement is stored by Engine
    When the user sets a fixed downtime of 3 minutes on rbt-passive-svc
    Then the service is marked as in downtime and the downtime is started by Engine
    When the user adds a comment from the Timeline tab of rbt-passive-svc
    Then the comment is saved and stored by Engine
    When the user forces a check on rbt-action-host
    Then the last check time of the host is refreshed
    And the Timeline tab of rbt-passive-svc lists the acknowledgement, the downtime and the comment
    When the downtime end time is reached and the user disacknowledges rbt-passive-svc
    Then the service is no longer marked as in downtime nor acknowledged
