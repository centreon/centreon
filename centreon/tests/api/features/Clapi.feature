Feature:
  As a Centreon admin
  I want to configure my centreon by command line
  To industrialize it

  Background:
    Given a running instance of Centreon Web API

  Scenario: We cannot import two identical relations between Service Template and Host Template
    Given I am logged in
    And the CLAPI import should success with these data:
    """
    HTPL;ADD;HostTemplate1;Host template 1;;;;
    HTPL;ADD;HostTemplate2;Host template 2;;;;
    STPL;ADD;ServiceTemplate1;ServiceTemplate1;
    STPL;ADD;ServiceTemplate2;ServiceTemplate2;
    STPL;addhosttemplate;ServiceTemplate1;HostTemplate1
    STPL;addhosttemplate;ServiceTemplate1;HostTemplate2
    STPL;addhosttemplate;ServiceTemplate2;HostTemplate1
    STPL;addhosttemplate;ServiceTemplate2;HostTemplate2
    """

    And the CLAPI import should fail with these data:
    """
    STPL;addhosttemplate;ServiceTemplate2;HostTemplate2
    """
    And the CLAPI import output should contain "Line 1 : Object already exists"

    And the CLAPI export of "STPL" filtered on "addhosttemplate;ServiceTemplate" should be
    """
    STPL;addhosttemplate;ServiceTemplate1;HostTemplate1
    STPL;addhosttemplate;ServiceTemplate1;HostTemplate2
    STPL;addhosttemplate;ServiceTemplate2;HostTemplate1
    STPL;addhosttemplate;ServiceTemplate2;HostTemplate2
    """
