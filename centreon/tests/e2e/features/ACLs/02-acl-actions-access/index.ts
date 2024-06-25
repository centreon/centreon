import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import data from '../../../fixtures/acls/acl-data.json';
import { ACLActionType, Action } from '../commands';

const ACLAction: ACLActionType = {
  ACLGroups: [data.ACLGroups.ACLGroup1.name, data.ACLGroups.ACLGroup2.name],
  actions: ['top_counter'],
  description: 'This is just a description',
  name: 'ACL_Action_1'
};

const modifiedACLAction = {
  actions: ['top_counter', 'poller_stats'],
  description: 'This is just a description modified',
  name: 'ACL_Action_1_modified',
  status: 'Disabled'
};

const duplicatedACLAction = {
  name: `${ACLAction.name}_1`
};

const allActions: Array<Action> = [
  'top_counter',
  'poller_stats',
  'poller_listing',
  'create_edit_poller_cfg',
  'delete_poller_cfg',
  'generate_cfg',
  'generate_trap',
  'global_shutdown',
  'global_restart',
  'global_notifications',
  'global_service_checks',
  'global_service_passive_checks',
  'global_host_checks',
  'global_host_passive_checks',
  'global_event_handler',
  'global_flap_detection',
  'global_service_obsess',
  'global_host_obsess',
  'global_perf_data',
  'service_checks',
  'service_notifications',
  'service_acknowledgement',
  'service_disacknowledgement',
  'service_schedule_check',
  'service_schedule_forced_check',
  'service_schedule_downtime',
  'service_comment',
  'service_event_handler',
  'service_flap_detection',
  'service_passive_checks',
  'service_submit_result',
  'service_display_command',
  'host_checks',
  'host_notifications',
  'host_acknowledgement',
  'host_disacknowledgement',
  'host_schedule_check',
  'host_schedule_forced_check',
  'host_schedule_downtime',
  'host_comment',
  'host_event_handler',
  'host_flap_detection',
  'host_checks_for_services',
  'host_notifications_for_services',
  'host_submit_result',
  'manage_tokens'
];

const allActionsByLots: Array<Action> = [
  'all_engine',
  'all_host',
  'all_service'
];

beforeEach(() => {
  cy.startContainers();
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/userTimezone.php'
  }).as('getTimeZone');
});

afterEach(() => {
  cy.stopContainers();
});

Given('I am logged in a Centreon server', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
});

Given('one ACL access group including a non admin user exists', () => {
  cy.addContact({
    admin: data.contacts.contact1.admin,
    email: data.contacts.contact1.email,
    name: data.contacts.contact1.name,
    password: data.contacts.contact1.password
  }).then(() => {
    cy.addACLGroup({
      contacts: [data.contacts.contact1.name],
      name: data.ACLGroups.ACLGroup1.name
    });
  });
});

Given(
  'one ACL access group linked to a contact group including an admin user exists',
  () => {
    cy.addContact({
      admin: data.contacts.contact3.admin,
      email: data.contacts.contact3.email,
      name: data.contacts.contact3.name,
      password: data.contacts.contact3.password
    });
    cy.addContactGroup({
      contacts: [data.contacts.contact3.name],
      name: data.contactGroups.contactGroup1.name
    });
    cy.addACLGroup({
      contactGroups: [data.contactGroups.contactGroup1.name],
      name: data.ACLGroups.ACLGroup2.name
    });
  }
);

When('I add a new action access linked with the access groups', () => {
  cy.navigateTo({
    page: 'Actions Access',
    rootItemNumber: 4,
    subMenu: 'ACL'
  });
  cy.wait('@getTimeZone');

  cy.getIframeBody().contains('a', 'Add').click();
  cy.wait('@getTimeZone');

  cy.getIframeBody().find('input[name="acl_action_name"]').type(ACLAction.name);
  cy.getIframeBody()
    .find('input[name="acl_action_description"]')
    .type(ACLAction.description);

  ACLAction.ACLGroups.forEach((ACLGroup) => {
    cy.getIframeBody().find('select[name="acl_groups-f[]"]').select(ACLGroup);
    cy.getIframeBody().find('input[name="add"]').click();
  });

  ACLAction.actions.forEach((action) => {
    cy.getIframeBody().find(`input[name="${action}"]`).parent().click();
  });

  cy.getIframeBody().find('input[name="submitA"]').eq(0).click();
});

Then('the action access record is saved with its properties', () => {
  cy.wait('@getTimeZone');

  cy.getIframeBody().contains('td.ListColLeft > a', ACLAction.name).click();
  cy.wait('@getTimeZone');

  cy.getIframeBody()
    .find('input[name="acl_action_name"]')
    .should('have.value', ACLAction.name);

  cy.getIframeBody()
    .find('input[name="acl_action_description"]')
    .should('have.value', ACLAction.description);

  ACLAction.ACLGroups.forEach((ACLGroup) => {
    cy.getIframeBody()
      .find('select[name="acl_groups-t[]"]')
      .should('contain', ACLGroup);
  });

  ACLAction.actions.forEach((action) => {
    cy.getIframeBody().find(`input[name="${action}"]`).should('be.checked');
  });
});

Then(
  'all linked access group display the new actions access in authorized information tab',
  () => {
    ACLAction.ACLGroups.forEach((ACLGroup) => {
      cy.navigateTo({
        page: 'Access Groups',
        rootItemNumber: 4,
        subMenu: 'ACL'
      });
      cy.wait('@getTimeZone');

      cy.getIframeBody().contains('td.ListColLeft > a', ACLGroup).click();

      cy.wait('@getTimeZone');
      cy.getIframeBody().contains('a', 'Authorizations information').click();

      cy.getIframeBody()
        .find('select[name="actionAccess-t[]"]')
        .should('contain', ACLAction.name);
    });
  }
);

When(
  'I select one by one all action to authorize them in an action access record I create',
  () => {
    cy.navigateTo({
      page: 'Actions Access',
      rootItemNumber: 4,
      subMenu: 'ACL'
    });
    cy.wait('@getTimeZone');

    cy.getIframeBody().contains('a', 'Add').click();
    cy.wait('@getTimeZone');

    cy.getIframeBody()
      .find('input[name="acl_action_name"]')
      .type(ACLAction.name);
    cy.getIframeBody()
      .find('input[name="acl_action_description"]')
      .type(ACLAction.description);

    ACLAction.ACLGroups.forEach((ACLGroup) => {
      cy.getIframeBody().find('select[name="acl_groups-f[]"]').select(ACLGroup);
      cy.getIframeBody().find('input[name="add"]').click();
    });

    allActions.forEach((action) => {
      cy.getIframeBody().find(`input[name="${action}"]`).parent().click();
    });
  }
);

Then('all radio-buttons have to be checked', () => {
  allActions.forEach((action) => {
    cy.getIframeBody().find(`input[name="${action}"]`).should('be.checked');
  });
});

When('I check button-radio for a lot of actions', () => {
  cy.navigateTo({
    page: 'Actions Access',
    rootItemNumber: 4,
    subMenu: 'ACL'
  });
  cy.wait('@getTimeZone');

  cy.getIframeBody().contains('a', 'Add').click();
  cy.wait('@getTimeZone');

  cy.getIframeBody().find('input[name="acl_action_name"]').type(ACLAction.name);
  cy.getIframeBody()
    .find('input[name="acl_action_description"]')
    .type(ACLAction.description);

  ACLAction.ACLGroups.forEach((ACLGroup) => {
    cy.getIframeBody().find('select[name="acl_groups-f[]"]').select(ACLGroup);
    cy.getIframeBody().find('input[name="add"]').click();
  });

  allActionsByLots.forEach((action) => {
    cy.getIframeBody().find(`input[name="${action}"]`).parent().click();
  });
});

Then('all buttons-radio of the authorized actions lot are checked', () => {
  allActions
    .filter(
      (action) =>
        action.startsWith('global') ||
        action.startsWith('service') ||
        action.startsWith('host')
    )
    .forEach((action) => {
      cy.getIframeBody().find(`input[name="${action}"]`).should('be.checked');
    });
});

Given('one existing action access', () => {
  cy.addACLAction({
    actions: ACLAction.actions,
    description: ACLAction.description,
    name: ACLAction.name
  });

  cy.addACLActionToACLGroup({
    ACLActionName: ACLAction.name,
    ACLGroupName: data.ACLGroups.ACLGroup1.name
  });
  cy.addACLActionToACLGroup({
    ACLActionName: ACLAction.name,
    ACLGroupName: data.ACLGroups.ACLGroup2.name
  });
});

When('I remove the access group', () => {
  cy.navigateTo({
    page: 'Actions Access',
    rootItemNumber: 4,
    subMenu: 'ACL'
  });
  cy.wait('@getTimeZone');

  cy.getIframeBody().contains('td.ListColLeft > a', ACLAction.name).click();

  cy.wait('@getTimeZone');
  cy.getIframeBody()
    .find('select[name="acl_groups-t[]"]')
    .select(data.ACLGroups.ACLGroup1.name);
  cy.getIframeBody().find('input[name="remove"]').click();

  cy.getIframeBody().find('input[name="submitC"]').eq(0).click();
});

Then(
  'the link between the access group and the action access is voided',
  () => {
    cy.navigateTo({
      page: 'Access Groups',
      rootItemNumber: 4,
      subMenu: 'ACL'
    });

    cy.wait('@getTimeZone').then(() => {
      cy.executeActionOnIframe(
        data.ACLGroups.ACLGroup1.name,
        ($body) => {
          cy.wrap($body)
            .contains('td.ListColLeft > a', data.ACLGroups.ACLGroup1.name)
            .click();
        },
        3,
        3000
      );
    });

    cy.wait('@getTimeZone').then(() => {
      cy.executeActionOnIframe(
        'Authorizations information',
        ($body) => {
          cy.wrap($body).contains('a', 'Authorizations information').click();
        },
        3,
        3000
      );
    });

    cy.getIframeBody()
      .find('select[name="actionAccess-t[]"]')
      .should('not.contain', ACLAction.name);
  }
);

When('I duplicate the action access', () => {
  cy.navigateTo({
    page: 'Actions Access',
    rootItemNumber: 4,
    subMenu: 'ACL'
  });
  cy.wait('@getTimeZone');

  cy.getIframeBody()
    .contains('tr', ACLAction.name)
    .within(() => {
      cy.get('input[type="checkbox"][name^="select"]').parent().click();
    });

  cy.get<HTMLIFrameElement>('iframe#main-content', { timeout: 10000 }).then(
    (iframe: JQuery<HTMLIFrameElement>) => {
      const win = iframe[0].contentWindow;

      if (!win) {
        throw new Error('Cannot get iframe');
      }

      cy.stub(win, 'confirm').returns(true);
    }
  );

  cy.getIframeBody().find('select[name="o1"]').select('Duplicate');
});

Then(
  'a new action access record is created with identical properties except the name',
  () => {
    cy.wait('@getTimeZone');

    const originalACLActionValues: Array<string> = [];
    cy.getIframeBody()
      .contains('tr', ACLAction.name)
      .within(() => {
        cy.get('td').each((td, index) => {
          if (index >= 1 && index <= 5)
            originalACLActionValues.push(td.text().trim());
        });
      });

    const duplicatedACLActionValues: Array<string> = [];
    cy.getIframeBody()
      .contains('tr', duplicatedACLAction.name)
      .within(() => {
        cy.get('td').each((td, index) => {
          if (index >= 1 && index <= 5)
            duplicatedACLActionValues.push(td.text().trim());
        });
      });

    cy.wrap(duplicatedACLActionValues).then((duplicatedValues) => {
      expect(duplicatedValues[0]).to.not.equal(originalACLActionValues[0]);
      for (let i = 1; i < originalACLActionValues.length; i += 1) {
        expect(duplicatedValues[i]).to.equal(originalACLActionValues[i]);
      }
    });
  }
);

When(
  'I modify some properties such as name, description, comments, status or authorized actions',
  () => {
    cy.navigateTo({
      page: 'Actions Access',
      rootItemNumber: 4,
      subMenu: 'ACL'
    });
    cy.wait('@getTimeZone');

    cy.getIframeBody().contains('td.ListColLeft > a', ACLAction.name).click();

    cy.wait('@getTimeZone');
    cy.getIframeBody()
      .find('input[name="acl_action_name"]')
      .type(`{selectAll}{backspace}${modifiedACLAction.name}`);
    cy.getIframeBody()
      .find('input[name="acl_action_description"]')
      .type(`{selectAll}{backspace}${modifiedACLAction.description}`);

    modifiedACLAction.actions.forEach((action) => {
      cy.getIframeBody().find(`input[name="${action}"]`).parent().click();
    });

    cy.getIframeBody()
      .find('input[name="acl_action_activate[acl_action_activate]"][value="0"]')
      .parent()
      .click();

    cy.getIframeBody().find('input[name="submitC"]').eq(1).click();
  }
);

Then('the modifications are saved', () => {
  cy.wait('@getTimeZone');

  const modifiedACLActionValues: Array<string> = [];
  cy.getIframeBody()
    .contains('tr', modifiedACLAction.name)
    .within(() => {
      cy.get('td').each((td, index) => {
        if (index >= 1 && index <= 5)
          modifiedACLActionValues.push(td.text().trim());
      });
    })
    .then(() => {
      // name
      expect(modifiedACLActionValues[0]).to.equal(modifiedACLAction.name);
      // description
      expect(modifiedACLActionValues[1]).to.equal(
        modifiedACLAction.description
      );
      // status
      expect(modifiedACLActionValues[2]).to.equal(modifiedACLAction.status);
    });

  cy.getIframeBody().contains('td.ListColLeft > a', ACLAction.name).click();
  cy.wait('@getTimeZone');

  // actions
  cy.getIframeBody()
    .find(`input[name="${modifiedACLAction.actions[0]}"]`)
    .should('not.be.checked');
  cy.getIframeBody()
    .find(`input[name="${modifiedACLAction.actions[1]}"]`)
    .should('be.checked');
});

When('I delete the action access', () => {
  cy.navigateTo({
    page: 'Actions Access',
    rootItemNumber: 4,
    subMenu: 'ACL'
  });
  cy.wait('@getTimeZone');

  cy.getIframeBody()
    .contains('tr', ACLAction.name)
    .within(() => {
      cy.get('input[type="checkbox"][name^="select"]').parent().click();
    });

  cy.get<HTMLIFrameElement>('iframe#main-content', { timeout: 10000 }).then(
    (iframe: JQuery<HTMLIFrameElement>) => {
      const win = iframe[0].contentWindow;

      if (!win) {
        throw new Error('Cannot get iframe');
      }

      cy.stub(win, 'confirm').returns(true);
    }
  );

  cy.getIframeBody().find('select[name="o1"]').select('Delete');
});

Then(
  'the action access record is not visible anymore in action access page',
  () => {
    cy.wait('@getTimeZone');

    cy.getIframeBody().should('not.contain', ACLAction.name);
  }
);

Then('the links with the acl groups are broken', () => {
  ACLAction.ACLGroups.forEach((ACLGroup) => {
    cy.navigateTo({
      page: 'Access Groups',
      rootItemNumber: 4,
      subMenu: 'ACL'
    });
    cy.wait('@getTimeZone');

    cy.getIframeBody().contains('td.ListColLeft > a', ACLGroup).click();

    cy.wait('@getTimeZone');
    cy.getIframeBody().contains('a', 'Authorizations information').click();

    cy.getIframeBody()
      .find('select[name="actionAccess-t[]"]')
      .should('not.contain', ACLAction.name);
  });
});
