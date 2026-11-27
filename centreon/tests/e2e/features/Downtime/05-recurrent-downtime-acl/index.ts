import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

const restrictedUser = 'recurrent-downtime-acl-user';
const aclProfile =
  'resources/clapi/config-ACL/recurrent-downtime-acl-user.json';

// The ACL profile grants host-granted only
const downtimes = {
  outside: { host: 'host-denied', name: 'dt-denied' },
  within: { host: 'host-granted', name: 'dt-granted' }
};

const refusalMessage =
  "Some recurring downtimes were not processed because you don't have access to all of their resources";

// Links of the listing row, enable is only offered on a disabled downtime and disable on an enabled one
const rowActionSelectors: Record<string, string> = {
  disable: 'a[href*="&o=u&"]',
  enable: 'a[href*="&o=e&"]'
};

const massActionLabels: Record<string, string> = {
  delete: 'Delete',
  disable: 'Disable',
  duplicate: 'Duplicate',
  enable: 'Enable'
};

const statusLabels: Record<string, string> = {
  disabled: 'Disabled',
  enabled: 'Enabled'
};

const openRecurrentDowntimes = (): void => {
  cy.visit(PAGES.monitoring.recurrentDowntimesLegacy);
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'table.ListTable');
};

const openRecurrentDowntimesAsRestrictedUser = (): void => {
  cy.logout();
  cy.loginByTypeOfUser({ jsonName: restrictedUser, loginViaApi: false });
  openRecurrentDowntimes();
};

// Every action reloads the listing: wait for it before asserting, or the old page would be checked
const waitForListingReload = (): void => {
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'table.ListTable');
};

const findRowByName = (
  $table: JQuery<HTMLElement>,
  name: string
): JQuery<HTMLElement> =>
  $table
    .find('tr')
    .filter(
      (_index, row) =>
        Cypress.$(row).find('td.ListColLeft > a').first().text().trim() === name
    );

// The listing table always exists, so these checks fail if the listing is not displayed
const checkListedDowntime = (
  name: string,
  check: ($row: JQuery<HTMLElement>) => void
): void => {
  cy.getIframeBody()
    .find('table.ListTable')
    .should(($table) => check(findRowByName($table, name)));
};

const getDowntimeRow = (
  name: string
): Cypress.Chainable<JQuery<HTMLTableRowElement>> =>
  cy
    .getIframeBody()
    .find('table.ListTable td.ListColLeft > a')
    .filter((_index, link) => link.textContent?.trim() === name)
    .closest('tr');

const addRecurrentDowntime = ({
  host,
  name
}: {
  host: string;
  name: string;
}): void => {
  cy.executeActionViaClapi({
    bodyContent: {
      action: 'ADD',
      object: 'DOWNTIME',
      values: `${name};${name}`
    }
  });
  cy.executeActionViaClapi({
    bodyContent: {
      action: 'ADDHOST',
      object: 'DOWNTIME',
      values: `${name};${host}`
    }
  });
};

const applyRowActionAsRestrictedUser = (name: string, action: string): void => {
  openRecurrentDowntimesAsRestrictedUser();
  getDowntimeRow(name).find(rowActionSelectors[action]).click();
  waitForListingReload();
};

const selectDowntime = (name: string): void => {
  getDowntimeRow(name)
    .find('input[type="checkbox"][name^="select"]')
    .check({ force: true });
};

const applyMassActionToBothDowntimes = (action: string): void => {
  selectDowntime(downtimes.within.name);
  selectDowntime(downtimes.outside.name);

  // Duplicate and delete ask for a confirmation
  cy.get<HTMLIFrameElement>('iframe#main-content', { timeout: 10000 }).then(
    (iframe: JQuery<HTMLIFrameElement>) => {
      const win = iframe[0].contentWindow;

      if (!win) {
        throw new Error('Cannot get iframe');
      }

      cy.stub(win, 'confirm').returns(true);
    }
  );

  cy.getIframeBody().find('select[name="o1"]').select(massActionLabels[action]);
  waitForListingReload();
};

const checkDowntimeIsUnchanged = (name: string, status: string): void => {
  checkListedDowntime(name, ($row) => {
    expect($row, `${name} is still listed`).to.have.length(1);
    expect($row.find('td.ListColCenter').text().trim()).to.equal(
      statusLabels[status]
    );
  });
  checkListedDowntime(`${name}_1`, ($row) => {
    expect($row, `${name} is not duplicated`).to.have.length(0);
  });
};

const checkDowntimeOutcome = (name: string, outcome: string): void => {
  if (outcome === 'deleted') {
    checkListedDowntime(name, ($row) => {
      expect($row, `${name} is deleted`).to.have.length(0);
    });

    return;
  }

  if (outcome === 'duplicated') {
    checkListedDowntime(`${name}_1`, ($row) => {
      expect($row, `${name} is duplicated`).to.have.length(1);
    });

    return;
  }

  checkListedDowntime(name, ($row) => {
    expect($row.find('td.ListColCenter').text().trim()).to.equal(
      statusLabels[outcome]
    );
  });
};

beforeEach(() => {
  cy.startContainers();
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.pages.time_zone
  }).as('getTimeZone');
});

afterEach(() => {
  cy.stopContainers();
});

Given('an administrator is logged in', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: true });
});

Given('a restricted user has access to one host only', () => {
  cy.setUserTokenApiV1();

  Object.values(downtimes).forEach(({ host }) => {
    cy.addHost({
      hostGroup: 'Linux-Servers',
      name: host,
      template: 'generic-host'
    });
  });

  cy.applyAclProfile(aclProfile);
});

Given(
  "a recurrent downtime exists on the host within the restricted user's access",
  () => {
    addRecurrentDowntime(downtimes.within);
  }
);

Given(
  "a recurrent downtime exists on a host outside the restricted user's access",
  () => {
    addRecurrentDowntime(downtimes.outside);
  }
);

Given('the recurrent downtimes are {word}', (status: string) => {
  Object.values(downtimes).forEach(({ name }) => {
    cy.executeActionViaClapi({
      bodyContent: {
        action: status === 'enabled' ? 'ENABLE' : 'DISABLE',
        object: 'DOWNTIME',
        values: name
      }
    });
  });
});

When(
  'the restricted user applies the {string} action to the recurrent downtime within their access',
  (action: string) => {
    applyRowActionAsRestrictedUser(downtimes.within.name, action);
  }
);

When(
  'the restricted user applies the {string} action to the recurrent downtime outside their access',
  (action: string) => {
    applyRowActionAsRestrictedUser(downtimes.outside.name, action);
  }
);

When(
  'the restricted user applies the {string} mass action to both recurrent downtimes',
  (action: string) => {
    openRecurrentDowntimesAsRestrictedUser();
    applyMassActionToBothDowntimes(action);
  }
);

When(
  'the super admin applies the {string} mass action to both recurrent downtimes',
  (action: string) => {
    openRecurrentDowntimes();
    applyMassActionToBothDowntimes(action);
  }
);

Then(
  'the recurrent downtime within their access is {word}',
  (outcome: string) => {
    checkDowntimeOutcome(downtimes.within.name, outcome);
  }
);

Then(
  'the recurrent downtime outside their access is still {word} and not duplicated',
  (status: string) => {
    checkDowntimeIsUnchanged(downtimes.outside.name, status);
  }
);

Then('both recurrent downtimes are deleted', () => {
  Object.values(downtimes).forEach(({ name }) => {
    checkDowntimeOutcome(name, 'deleted');
  });
});

Then('a refusal message is displayed', () => {
  cy.getIframeBody().contains(refusalMessage).should('be.visible');
});

Then('no refusal message is displayed', () => {
  cy.getIframeBody().find('table.ListTable').should('exist');
  cy.getIframeBody().should('not.contain.text', refusalMessage);
});
