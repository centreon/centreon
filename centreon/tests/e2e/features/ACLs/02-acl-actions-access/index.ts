import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import { PAGES } from 'fixtures/shared/constants/pages';
import data from '../../../fixtures/acls/acl-data.json';
import { AclActionType, Action } from '../commands';

const aclAction: AclActionType = {
  aclGroups: [data.ACLGroups.ACLGroup1.name, data.ACLGroups.ACLGroup2.name],
  actions: ['top_counter'],
  description: 'This is just a description',
  name: 'ACL_Action_1'
};

const modifiedAclAction = {
  actions: ['top_counter', 'poller_stats'],
  description: 'This is just a description modified',
  name: 'ACL_Action_1_modified',
  status: 'Disabled'
};

const duplicatedAclAction = {
  name: `${aclAction.name}_1`
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
  cy.visit(PAGES.configuration.aclActionsAccessLegacy);
  cy.wait('@getTimeZone');

  cy.getIframeBody().contains('a', 'Add').click();
  cy.wait('@getTimeZone');

  cy.getIframeBody().find('input[name="acl_action_name"]').type(aclAction.name);
  cy.getIframeBody()
    .find('input[name="acl_action_description"]')
    .type(aclAction.description);

  aclAction.aclGroups.forEach((aclGroup) => {
    cy.getIframeBody().find('select[name="acl_groups-f[]"]').select(aclGroup);
    cy.getIframeBody().find('input[name="add"]').click();
  });

  aclAction.actions.forEach((action) => {
    cy.getIframeBody().find(`input[name="${action}"]`).parent().click();
  });

  cy.getIframeBody().find('input[name="submitA"]').eq(0).click();
});

Then('the action access record is saved with its properties', () => {
  cy.wait('@getTimeZone');

  cy.getIframeBody().contains('td.ListColLeft > a', aclAction.name).click();
  cy.wait('@getTimeZone');

  cy.getIframeBody()
    .find('input[name="acl_action_name"]')
    .should('have.value', aclAction.name);

  cy.getIframeBody()
    .find('input[name="acl_action_description"]')
    .should('have.value', aclAction.description);

  aclAction.aclGroups.forEach((aclGroup) => {
    cy.getIframeBody()
      .find('select[name="acl_groups-t[]"]')
      .should('contain', aclGroup);
  });

  aclAction.actions.forEach((action) => {
    cy.getIframeBody().find(`input[name="${action}"]`).should('be.checked');
  });
});

Then(
  'all linked access group display the new actions access in authorized information tab',
  () => {
    aclAction.aclGroups.forEach((aclGroup) => {
      cy.visit(PAGES.configuration.aclAccessGroupsLegacy);
      cy.wait('@getTimeZone');

      cy.getIframeBody().contains('td.ListColLeft > a', aclGroup).click();

      cy.wait('@getTimeZone');
      cy.getIframeBody().contains('a', 'Authorizations information').click();

      cy.getIframeBody()
        .find('select[name="actionAccess-t[]"]')
        .should('contain', aclAction.name);
    });
  }
);

When(
  'I select one by one all action to authorize them in an action access record I create',
  () => {
    cy.visit(PAGES.configuration.aclActionsAccessLegacy);
    cy.wait('@getTimeZone');

    cy.getIframeBody().contains('a', 'Add').click();
    cy.wait('@getTimeZone');

    cy.getIframeBody()
      .find('input[name="acl_action_name"]')
      .type(aclAction.name);
    cy.getIframeBody()
      .find('input[name="acl_action_description"]')
      .type(aclAction.description);

    aclAction.aclGroups.forEach((aclGroup) => {
      cy.getIframeBody().find('select[name="acl_groups-f[]"]').select(aclGroup);
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
  cy.visit(PAGES.configuration.aclActionsAccessLegacy);
  cy.wait('@getTimeZone');

  cy.getIframeBody().contains('a', 'Add').click();
  cy.wait('@getTimeZone');

  cy.getIframeBody().find('input[name="acl_action_name"]').type(aclAction.name);
  cy.getIframeBody()
    .find('input[name="acl_action_description"]')
    .type(aclAction.description);

  aclAction.aclGroups.forEach((aclGroup) => {
    cy.getIframeBody().find('select[name="acl_groups-f[]"]').select(aclGroup);
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
    actions: aclAction.actions,
    description: aclAction.description,
    name: aclAction.name
  });

  cy.addAclActionToAclGroup({
    aclActionName: aclAction.name,
    aclGroupName: data.ACLGroups.ACLGroup1.name
  });
  cy.addAclActionToAclGroup({
    aclActionName: aclAction.name,
    aclGroupName: data.ACLGroups.ACLGroup2.name
  });
});

When('I remove the access group', () => {
  cy.visit(PAGES.configuration.aclActionsAccessLegacy);
  cy.wait('@getTimeZone');

  cy.getIframeBody().contains('td.ListColLeft > a', aclAction.name).click();

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
    cy.visit(PAGES.configuration.aclAccessGroupsLegacy);

    cy.wait('@getTimeZone').then(() => {
      cy.executeActionOnIframe(
        data.ACLGroups.ACLGroup1.name,
        (body) => {
          cy.wrap(body)
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
        (body) => {
          cy.wrap(body).contains('a', 'Authorizations information').click();
        },
        3,
        3000
      );
    });

    cy.getIframeBody()
      .find('select[name="actionAccess-t[]"]')
      .should('not.contain', aclAction.name);
  }
);

When('I duplicate the action access', () => {
  cy.visit(PAGES.configuration.aclActionsAccessLegacy);
  cy.wait('@getTimeZone');

  cy.getIframeBody()
    .contains('tr', aclAction.name)
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

    const originalAclActionValues: Array<string> = [];
    cy.getIframeBody()
      .contains('tr', aclAction.name)
      .within(() => {
        cy.get('td').each((td, index) => {
          if (index >= 1 && index <= 5)
            originalAclActionValues.push(td.text().trim());
        });
      });

    const duplicatedAclActionValues: Array<string> = [];
    cy.getIframeBody()
      .contains('tr', duplicatedAclAction.name)
      .within(() => {
        cy.get('td').each((td, index) => {
          if (index >= 1 && index <= 5)
            duplicatedAclActionValues.push(td.text().trim());
        });
      });

    cy.wrap(duplicatedAclActionValues).then((duplicatedValues) => {
      expect(duplicatedValues[0]).to.not.equal(originalAclActionValues[0]);
      for (let i = 1; i < originalAclActionValues.length; i += 1) {
        expect(duplicatedValues[i]).to.equal(originalAclActionValues[i]);
      }
    });
  }
);

When(
  'I modify some properties such as name, description, comments, status or authorized actions',
  () => {
    cy.visit(PAGES.configuration.aclActionsAccessLegacy);
    cy.wait('@getTimeZone');
    cy.getIframeBody().contains('td.ListColLeft > a', aclAction.name).click();
    cy.wait('@getTimeZone');
    cy.getIframeBody()
      .find('input[name="acl_action_name"]')
      .type(`{selectAll}{backspace}${modifiedAclAction.name}`);
    cy.getIframeBody()
      .find('input[name="acl_action_description"]')
      .type(`{selectAll}{backspace}${modifiedAclAction.description}`);

    modifiedAclAction.actions.forEach((action) => {
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

  const modifiedAclActionValues: Array<string> = [];
  cy.getIframeBody()
    .contains('tr', modifiedAclAction.name)
    .within(() => {
      cy.get('td').each((td, index) => {
        if (index >= 1 && index <= 5)
          modifiedAclActionValues.push(td.text().trim());
      });
    })
    .then(() => {
      // name
      expect(modifiedAclActionValues[0]).to.equal(modifiedAclAction.name);
      // description
      expect(modifiedAclActionValues[1]).to.equal(
        modifiedAclAction.description
      );
      // status
      expect(modifiedAclActionValues[2]).to.equal(modifiedAclAction.status);
    });

  cy.getIframeBody().contains('td.ListColLeft > a', aclAction.name).click();
  cy.wait('@getTimeZone');

  // actions
  cy.getIframeBody()
    .find(`input[name="${modifiedAclAction.actions[0]}"]`)
    .should('not.be.checked');
  cy.getIframeBody()
    .find(`input[name="${modifiedAclAction.actions[1]}"]`)
    .should('be.checked');
});

When('I delete the action access', () => {
  cy.visit(PAGES.configuration.aclActionsAccessLegacy);
  cy.wait('@getTimeZone');

  cy.getIframeBody()
    .contains('tr', aclAction.name)
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

    cy.getIframeBody().should('not.contain', aclAction.name);
  }
);

Then('the links with the acl groups are broken', () => {
  aclAction.aclGroups.forEach((aclGroup) => {
    cy.visit(PAGES.configuration.aclAccessGroupsLegacy);
    cy.wait('@getTimeZone');

    cy.getIframeBody().contains('td.ListColLeft > a', aclGroup).click();

    cy.wait('@getTimeZone');
    cy.getIframeBody().contains('a', 'Authorizations information').click();

    cy.getIframeBody()
      .find('select[name="actionAccess-t[]"]')
      .should('not.contain', aclAction.name);
  });
});
