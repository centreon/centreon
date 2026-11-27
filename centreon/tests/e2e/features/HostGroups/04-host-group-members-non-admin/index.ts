import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import { checkHostsAreMonitored } from '../../../commons';
import {
  buildContactIdQuery,
  buildHostGroupChangeLogsQuery,
  buildHostGroupMembersQuery,
  buildReachableHostsQuery,
  duplicates,
  lowestReachableHostsCount
} from '../common';

interface ReachableHost {
  host_id: number;
  name: string;
}

interface HostGroupMember {
  host_id: number;
  host_name: string;
}

const user = 'rbt-hg-user';
const userPassword = 'Centreon!2021RbtHg';
const aclGroup = 'rbt-hg-group';
const aclMenu = 'rbt-hg-menus';
const aclResources = 'rbt-hg-resources';
const hostPrefix = 'rbt-hg-host-';
// Enough hosts for several of them to be beyond the 10 lowest reachable ids
const hostCount = 14;
const userHostGroup = 'HG-TEST';
const adminHostGroup = 'rbt-hg-admin';
const modifyFormTitle = 'Modify a host group';

const hostNames = Array.from(
  { length: hostCount },
  (_, index) => `${hostPrefix}${String(index + 1).padStart(2, '0')}`
);

let platformReady = false;
let hostGroupReady = false;
let loggedUser = '';
let withinHosts: Array<ReachableHost> = [];
let beyondHosts: Array<ReachableHost> = [];
let reachableCount = 0;
let changedHost: ReachableHost | null = null;
let changedHostGroup = '';

beforeEach(() => {
  cy.startContainers();

  platformReady = false;
  hostGroupReady = false;
  loggedUser = '';
  withinHosts = [];
  beyondHosts = [];
  reachableCount = 0;
  changedHost = null;
  changedHostGroup = '';

  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.pages.time_zone
  }).as('getTimeZone');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.api.hosts_configuration}/groups?page=1&limit=*`
  }).as('getGroups');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.api.hosts_configuration}/groups/*`
  }).as('getGroupDetails');
  cy.intercept({
    method: 'PUT',
    url: `${INTERCEPTORS.api.hosts_configuration}/groups/*`
  }).as('updateGroup');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.api.hosts_configuration}?*`
  }).as('getHosts');
});

afterEach(() => {
  cy.stopContainers();
});

const clapi = (action: string, object: string, values: string): void => {
  cy.executeActionViaClapi({ bodyContent: { action, object, values } });
};

const addHosts = (): void => {
  hostNames.forEach((host) => {
    clapi('ADD', 'HOST', `${host};${host};127.0.0.1;generic-host;Central;`);
  });
};

// Hosts monitored, user and access group computed: the host group update of
// a non-admin user only removes the members its access group reaches
const preparePlatform = (): void => {
  if (platformReady) {
    return;
  }
  platformReady = true;

  addHosts();
  clapi('ADD', 'HG', `${userHostGroup};${userHostGroup}`);
  cy.applyPollerConfiguration();
  checkHostsAreMonitored(hostNames.map((name) => ({ name })));

  clapi(
    'ADD',
    'CONTACT',
    `${user};${user};${user}@localhost;${userPassword};0;1;en_US;local`
  );
  cy.addACLMenu({ name: aclMenu, rule: ['Configuration', 'Hosts'] });
  cy.addACLResource({ name: aclResources });
  clapi('GRANT_HOST', 'ACLRESOURCE', `${aclResources};*`);
  clapi('GRANT_HOSTGROUP', 'ACLRESOURCE', `${aclResources};*`);
  clapi('ADDFILTER_INSTANCE', 'ACLRESOURCE', `${aclResources};Central`);
  cy.addACLGroup({ contacts: [user], name: aclGroup });
  clapi('ADDMENU', 'ACLGROUP', `${aclGroup};${aclMenu}`);
  clapi('ADDRESOURCE', 'ACLGROUP', `${aclGroup};${aclResources}`);
  clapi('RELOAD', 'ACL', '');
  cy.applyAcl();

  cy.requestOnDatabase({
    database: 'centreon_storage',
    query: buildReachableHostsQuery(aclGroup)
  }).then(([rows]) => {
    const reachable = (rows as Array<ReachableHost>).map(
      ({ host_id, name }) => ({ host_id: Number(host_id), name })
    );
    const isTestHost = ({ name }: ReachableHost): boolean =>
      name.startsWith(hostPrefix);

    reachableCount = reachable.length;
    withinHosts = reachable
      .slice(0, lowestReachableHostsCount)
      .filter(isTestHost);
    beyondHosts = reachable.slice(lowestReachableHostsCount).filter(isTestHost);

    expect(withinHosts.length, 'test hosts within the 10 lowest ids').to.be.gte(
      2
    );
    expect(beyondHosts.length, 'test hosts beyond the 10 lowest ids').to.be.gte(
      3
    );
  });
};

// HG-TEST holds two hosts within and two hosts beyond the 10 lowest ids
const prepareUserHostGroup = (): void => {
  preparePlatform();

  if (hostGroupReady) {
    return;
  }
  hostGroupReady = true;

  cy.then(() => {
    const members = [...withinHosts.slice(0, 2), ...beyondHosts.slice(0, 2)];

    clapi(
      'ADDMEMBER',
      'HG',
      `${userHostGroup};${members.map(({ name }) => name).join('|')}`
    );
  });
};

const loginAs = (login: string): void => {
  if (loggedUser === login) {
    return;
  }
  loggedUser = login;

  cy.clearCookies();
  if (login === 'admin') {
    cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: true });

    return;
  }
  cy.loginByCredentials({ login, password: userPassword });
};

const openHostGroup = (hostGroup: string): void => {
  cy.visit(PAGES.configuration.hostGroups);
  cy.wait('@getGroups');
  cy.contains('p', hostGroup).click();
  cy.wait('@getGroupDetails');
  cy.contains(modifyFormTitle).should('be.visible');
};

const memberChip = (hostId: number): Cypress.Chainable =>
  cy.get(`[data-testid="tag-option-chip-${hostId}"]`);

const saveHostGroup = (): void => {
  cy.get('button[data-testid="submit"]').click();
  cy.wait('@updateGroup').its('response.statusCode').should('eq', 204);
};

const removeMemberInForm = (hostGroup: string, host: ReachableHost): void => {
  changedHost = host;
  changedHostGroup = hostGroup;

  openHostGroup(hostGroup);
  memberChip(host.host_id).should('be.visible');
  memberChip(host.host_id).find('[data-testid="CancelIcon"]').click();
  memberChip(host.host_id).should('not.exist');
  saveHostGroup();
};

const hostGroupMembers = (
  hostGroup: string
): Cypress.Chainable<Array<HostGroupMember>> =>
  cy
    .requestOnDatabase({
      database: 'centreon',
      query: buildHostGroupMembersQuery(hostGroup)
    })
    .then(([rows]) =>
      cy.wrap(
        (rows as Array<HostGroupMember>).map(({ host_id, host_name }) => ({
          host_id: Number(host_id),
          host_name
        })),
        { log: false }
      )
    );

const changedHostOrFail = (): ReachableHost => {
  if (changedHost === null) {
    throw new Error('No host was changed in the host group');
  }

  return changedHost;
};

Given(
  'a non-admin user whose ACL grants write access to hosts and host groups on all resources',
  () => {
    preparePlatform();
  }
);

Given('more than 10 hosts reachable by this user', () => {
  cy.then(() => {
    expect(reachableCount, `hosts reachable by ${user}`).to.be.greaterThan(
      lowestReachableHostsCount
    );
  });
});

Given(
  'a host group {string} containing hosts within and beyond the 10 lowest host ids reachable by the user',
  (hostGroup: string) => {
    expect(hostGroup).to.eq(userHostGroup);

    prepareUserHostGroup();
  }
);

When(
  'the user removes from {string} a host that is not among the 10 lowest host ids reachable and saves',
  (hostGroup: string) => {
    loginAs(user);
    cy.then(() => removeMemberInForm(hostGroup, beyondHosts[0]));
  }
);

When(
  'the user removes from {string} a host among the 10 lowest host ids reachable and saves',
  (hostGroup: string) => {
    loginAs(user);
    cy.then(() => removeMemberInForm(hostGroup, withinHosts[0]));
  }
);

Then('the host is no longer a member of {string}', (hostGroup: string) => {
  hostGroupMembers(hostGroup).then((members) => {
    const host = changedHostOrFail();

    expect(
      members.map(({ host_id }) => host_id),
      `members of ${hostGroup}`
    ).not.to.include(host.host_id);
    expect(members, `members left in ${hostGroup}`).not.to.have.length(0);
  });
});

Then('an entry is logged for this change in Administration > Logs', () => {
  cy.requestOnDatabase({
    database: 'centreon',
    query: buildContactIdQuery(user)
  }).then(([contacts]) => {
    expect(contacts, `contact ${user}`).to.have.length(1);

    cy.requestOnDatabase({
      database: 'centreon_storage',
      query: buildHostGroupChangeLogsQuery(
        changedHostGroup,
        Number(contacts[0].contact_id)
      )
    }).then(([rows]) => {
      const removedLogs = (
        rows as Array<{ field_name: string; field_value: string }>
      ).filter(({ field_name }) => field_name === 'hosts_removed');

      expect(removedLogs, 'hosts_removed entries').to.have.length(1);
      expect(removedLogs[0].field_value.split(',')).to.include(
        String(changedHostOrFail().host_id)
      );
    });
  });
});

Given('a host group containing more than 10 hosts', () => {
  addHosts();
  clapi('ADD', 'HG', `${adminHostGroup};${adminHostGroup}`);
  clapi('ADDMEMBER', 'HG', `${adminHostGroup};${hostNames.join('|')}`);

  hostGroupMembers(adminHostGroup).then((members) => {
    expect(members.length, `members of ${adminHostGroup}`).to.be.greaterThan(
      lowestReachableHostsCount
    );
  });
});

When('an admin user removes a host from this host group and saves', () => {
  loginAs('admin');

  hostGroupMembers(adminHostGroup).then((members) => {
    // The member with the highest id, beyond the first ten
    const last = members[members.length - 1];

    removeMemberInForm(adminHostGroup, {
      host_id: last.host_id,
      name: last.host_name
    });
  });
});

Then('the host is no longer a member of the host group', () => {
  hostGroupMembers(adminHostGroup).then((members) => {
    expect(
      members.map(({ host_id }) => host_id),
      `members of ${adminHostGroup}`
    ).not.to.include(changedHostOrFail().host_id);
    expect(members, `members left in ${adminHostGroup}`).to.have.length(
      hostCount - 1
    );
  });
});

Given(
  'a host beyond the 10 lowest host ids reachable by the user, member of {string}',
  (hostGroup: string) => {
    expect(hostGroup).to.eq(userHostGroup);

    prepareUserHostGroup();
    cy.then(() => {
      changedHost = beyondHosts[0];
      changedHostGroup = hostGroup;
    });
  }
);

When(
  'the user removes the {string} relation from the Relations tab of this host and saves',
  (hostGroup: string) => {
    loginAs(user);

    cy.then(() => {
      cy.visit(
        `${PAGES.configuration.hostsLegacy}&o=c&host_id=${changedHostOrFail().host_id}`
      );
    });
    cy.wait('@getTimeZone');
    cy.waitForElementInIframe('#main-content', 'input[name="host_name"]');
    cy.getIframeBody().contains('a', 'Relations').click();
    cy.getIframeBody()
      .find(`li.select2-selection__choice[title="${hostGroup}"]`)
      .find('.select2-selection__choice__remove')
      .click({ force: true });
    cy.getIframeBody()
      .find(`li.select2-selection__choice[title="${hostGroup}"]`)
      .should('not.exist');
    cy.getIframeBody()
      .find('input.btc.bt_success[name^="submit"]')
      .eq(0)
      .click();
    cy.wait('@getTimeZone');
  }
);

When(
  'the user adds to {string} a host beyond the 10 lowest host ids reachable and saves',
  (hostGroup: string) => {
    loginAs(user);

    cy.then(() => {
      const host = beyondHosts[beyondHosts.length - 1];

      changedHost = host;
      changedHostGroup = hostGroup;

      openHostGroup(hostGroup);
      cy.get('#Selecthosts').click();
      cy.get('#Selecthosts').type(host.name);
      cy.wait('@getHosts');
      cy.contains('[role="option"]', host.name).click();
      // Close the list of hosts
      cy.contains(modifyFormTitle).click();
      memberChip(host.host_id).should('be.visible');
      saveHostGroup();
    });
  }
);

Then('the host is a member of {string}', (hostGroup: string) => {
  hostGroupMembers(hostGroup).then((members) => {
    expect(
      members.map(({ host_id }) => host_id),
      `members of ${hostGroup}`
    ).to.include(changedHostOrFail().host_id);
  });
});

Given(
  'a host was removed from {string} by the non-admin user',
  (hostGroup: string) => {
    expect(hostGroup).to.eq(userHostGroup);

    prepareUserHostGroup();
    loginAs(user);
    cy.then(() => removeMemberInForm(hostGroup, beyondHosts[0]));
  }
);

When('{string} is reopened', (hostGroup: string) => {
  openHostGroup(hostGroup);
});

Then('no host appears twice in the members list', () => {
  cy.get('[data-testid^="tag-option-chip-"]')
    .should('have.length.greaterThan', 0)
    .then((chips) => {
      const chipIds = chips
        .toArray()
        .map((chip) => chip.getAttribute('data-testid') ?? '');

      expect(duplicates(chipIds), 'members listed twice').to.deep.eq([]);
      expect(chipIds, 'removed member').not.to.include(
        `tag-option-chip-${changedHostOrFail().host_id}`
      );
    });

  hostGroupMembers(changedHostGroup).then((members) => {
    expect(
      duplicates(members.map(({ host_id }) => String(host_id))),
      `duplicated rows of ${changedHostGroup}`
    ).to.deep.eq([]);
  });
});
