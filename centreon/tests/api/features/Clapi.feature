Feature:
  As a Centreon admin
  I want to configure my centreon by command line
  To industrialize it

  Background:
    Given a running instance of Centreon Web API

  Scenario: We cannot import two identical relations between Service Template and Host Template
    Given I am logged in
    And the CLAPI commands should NOT have an output with these data:
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

    And the CLAPI commands should have an output with these data:
    """
    STPL;addhosttemplate;ServiceTemplate2;HostTemplate2
    """
    And the CLAPI commands output should contain "Line 1 : Object already exists"

    And the CLAPI export of "STPL" filtered on "addhosttemplate;ServiceTemplate" should be
    """
    STPL;addhosttemplate;ServiceTemplate1;HostTemplate1
    STPL;addhosttemplate;ServiceTemplate1;HostTemplate2
    STPL;addhosttemplate;ServiceTemplate2;HostTemplate1
    STPL;addhosttemplate;ServiceTemplate2;HostTemplate2
    """


  Scenario: We must retrieve the correct host templates
    Given I am logged in
    And the CLAPI commands should NOT have an output with these data:
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

    And the CLAPI commands should have an output with these data:
    """
    STPL;gethosttemplate;ServiceTemplate1
    """
    And the CLAPI commands output should be
    """
    id;name
    15;HostTemplate1
    16;HostTemplate2
    """

    And the CLAPI commands should have an output with these data:
    """
    STPL;gethosttemplate;ServiceTemplate2
    """
    And the CLAPI commands output should be
    """
    id;name
    15;HostTemplate1
    16;HostTemplate2
    """
