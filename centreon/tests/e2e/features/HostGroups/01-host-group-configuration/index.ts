import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { checkHostsAreMonitored, checkServicesAreMonitored } from 'e2e/commons';

const hostGroup = 'host_group_for_test';
const services = {
    serviceCritical: {
        host: 'host3',
        name: 'service3',
        template: 'SNMP-Linux-Load-Average'
    },
    serviceOk: { host: 'host2', name: 'service_test_ok', template: 'Ping-LAN' },
    serviceWarning: {
        host: 'host2',
        name: 'service2',
        template: 'SNMP-Linux-Memory'
    }
};
const resultsToSubmit = [
    {
        host: services.serviceWarning.host,
        output: 'submit_status_2',
        service: services.serviceCritical.name,
        status: 'critical'
    },
    {
        host: services.serviceWarning.host,
        output: 'submit_status_2',
        service: services.serviceWarning.name,
        status: 'warning'
    },
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

Given('an admin user is logged in a Centreon server',
    () => {
        cy.loginByTypeOfUser({
            jsonName: 'admin',
            loginViaApi: false
        });
    });

When('a host group is configured',
    () => {
        cy.addHostGroup({
            name: hostGroup
        });

        cy.addHost({
            hostGroup: hostGroup,
            name: services.serviceOk.host,
            template: 'generic-host'
        })
            .addService({
                activeCheckEnabled: false,
                host: services.serviceOk.host,
                maxCheckAttempts: 1,
                name: services.serviceOk.name,
                template: services.serviceOk.template
            })
            .addService({
                activeCheckEnabled: false,
                host: services.serviceOk.host,
                maxCheckAttempts: 1,
                name: services.serviceWarning.name,
                template: services.serviceWarning.template
            })
            .addService({
                activeCheckEnabled: false,
                host: services.serviceOk.host,
                maxCheckAttempts: 1,
                name: services.serviceCritical.name,
                template: services.serviceCritical.template
            }).applyPollerConfiguration();

        checkHostsAreMonitored([
            { name: services.serviceOk.host },
        ]);
        checkServicesAreMonitored([
            { name: services.serviceCritical.name },
            { name: services.serviceOk.name }
        ]);
        cy.submitResults(resultsToSubmit);
    });

When('the user changes some properties of the configured host group "name, alias, comments"',
    () => {
        cy.navigateTo({
            page: "Host Groups",
            rootItemNumber: 3,
            subMenu: "Hosts",
        });
        cy.wait('@getTimeZone');
        cy.getIframeBody().contains(hostGroup).click({
            force: true,
        });
        cy.waitForElementInIframe('#main-content', 'input[name="hg_name"]');
        cy.getIframeBody().find('input[name="hg_name"]').clear().type(hostGroup + '_new');
        cy.getIframeBody().find('input[name="hg_alias"]').clear().type(hostGroup + '_new');
        cy.getIframeBody().find('textarea[name="hg_comment"]').clear().type('Host group updated for test!');
        cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(0).click();
        cy.wait('@getTimeZone');
    }
);

Then('the properties are updated',
    () => {
        cy.exportConfig();
        cy.getIframeBody().contains(hostGroup + '_new').should('exist');
    }
);

When('the user duplicates the configured host group',
    () => {
        cy.navigateTo({
            page: "Host Groups",
            rootItemNumber: 3,
            subMenu: "Hosts",
        });
        cy.wait('@getTimeZone');
        cy.getIframeBody().find('div.md-checkbox.md-checkbox-inline').eq(2).click();
        cy.getIframeBody().find('select').eq(0)
            .invoke('attr', 'onchange', "javascript: { setO(this.form.elements['o1'].value); this.form.submit(); }");
        cy.getIframeBody().find('select').eq(0).select('Duplicate');
        cy.wait('@getTimeZone');
        cy.exportConfig();
    }
);

Then('a new host group is created with identical properties',
    () => {
        cy.getIframeBody().contains(hostGroup + '_1').should('exist');
    }
);

When('the user deletes the configured host group',
    () => {
        cy.navigateTo({
            page: "Host Groups",
            rootItemNumber: 3,
            subMenu: "Hosts",
        });
        cy.wait('@getTimeZone');
        cy.getIframeBody().find('div.md-checkbox.md-checkbox-inline').eq(2).click();
        cy.getIframeBody().find('select').eq(0)
            .invoke('attr', 'onchange', "javascript: { setO(this.form.elements['o1'].value); this.form.submit(); }");
        cy.getIframeBody().find('select').eq(0).select('Delete');
        cy.wait('@getTimeZone');
        cy.exportConfig();
    }
);

Then('the configured host group is not visible anymore on host group page',
    () => {
        cy.getIframeBody().contains(hostGroup + '_1').should('not.exist');
    }
);