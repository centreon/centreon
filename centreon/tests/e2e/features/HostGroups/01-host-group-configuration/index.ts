import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { checkHostsAreMonitored, checkServicesAreMonitored } from 'e2e/commons';

const hostGroup = {
    name: 'host_group_for_test',
    alias: 'host_group_for_test',
    comment: 'Host group updated for test!',
    updatedName: 'host_group_for_test_new',
    duplicatedName: 'host_group_for_test_1'
};

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

const navigateAndSetOnChange = () => {
    cy.navigateTo({
        page: "Host Groups",
        rootItemNumber: 3,
        subMenu: "Hosts",
    });
    cy.wait('@getTimeZone');
    cy.getIframeBody().find('div.md-checkbox.md-checkbox-inline').eq(2).click();
    cy.getIframeBody().find('select').eq(0)
      .invoke('attr', 'onchange', "javascript: { setO(this.form.elements['o1'].value); this.form.submit(); }");
};

const checkIfEnableResourceIsCheckedOrDisable = (option, value, shouldBe) => {
    cy.getIframeBody().find('select[name="hg_hosts[]"]')
       .find('option')
        .then(options => {
            expect(options.length).to.eq(2);
            const host2Option = options.filter((index, option) => {
                return Cypress.$(option).text() === services.serviceOk.host;
            });
            expect(host2Option.length).to.eq(1);
        });
    cy.getIframeBody().contains('label', option)
        .should('exist')
        .then(($label) => {
            const radioId = $label.attr('for');
            cy.getIframeBody().find(`input[type="radio"][id="${radioId}"]`)
                .should(shouldBe)
                .and('have.value', value);
        });
};

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
            name: hostGroup.name
        });

        cy.addHost({
            hostGroup: hostGroup.name,
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
        cy.getIframeBody().contains(hostGroup.name).click({
            force: true,
        });

        cy.waitUntil(
            () => {
                return cy
                    .getByLabel({ label: 'Up status hosts', tag: 'a' })
                    .invoke('text')
                    .then((text) => {
                        if (text !== '2') {
                            cy.exportConfig();
                        }

                        return text === '2';
                    });
            },
            { interval: 20000, timeout: 100000 }
        );

        cy.getIframeBody().find('input[name="hg_name"]').clear().type(hostGroup.updatedName);
        cy.getIframeBody().find('input[name="hg_alias"]').clear().type(hostGroup.updatedName);
        cy.getIframeBody().find('textarea[name="hg_comment"]').clear().type(hostGroup.comment);
        cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(0).click();
        cy.wait('@getTimeZone');
    }
);

Then('these properties "name, alias, comments" are updated',
    () => {
        cy.exportConfig();
        cy.getIframeBody().contains(hostGroup.updatedName).should('exist');
        cy.getIframeBody().contains(hostGroup.updatedName).click();
        cy.waitForElementInIframe('#main-content', 'input[name="hg_name"]');
        cy.getIframeBody().find('input[name="hg_name"]').should('have.value', hostGroup.updatedName);
        cy.getIframeBody().find('input[name="hg_alias"]').should('have.value', hostGroup.updatedName);
        cy.getIframeBody().find('textarea[name="hg_comment"]').should('have.value', hostGroup.comment);
    }
);

When('the user duplicates the configured host group',
    () => {
        navigateAndSetOnChange();
        cy.getIframeBody().find('select').eq(0).select('Duplicate');
        cy.wait('@getTimeZone');
        cy.exportConfig();
    }
);

Then('a new host group is created with identical properties "name, alias, memebers, enabled disable resource"',
    () => {
        cy.getIframeBody().contains(hostGroup.duplicatedName).should('exist');
        cy.getIframeBody().contains(hostGroup.duplicatedName).click();
        cy.waitForElementInIframe('#main-content', 'input[name="hg_name"]');
        cy.getIframeBody().find('input[name="hg_name"]').should('have.value', hostGroup.duplicatedName);
        cy.getIframeBody().find('input[name="hg_alias"]').should('have.value', hostGroup.name);
        cy.getIframeBody().find('select[name="hg_hosts[]"]')
            .find('option')
            .then(options => {
                expect(options.length).to.eq(2);
                const host2Option = options.filter((index, option) => {
                    return Cypress.$(option).text() === services.serviceOk.host;
                });
                expect(host2Option.length).to.eq(1);
            });
        checkIfEnableResourceIsCheckedOrDisable('Enabled', 1, 'be.checked');
        checkIfEnableResourceIsCheckedOrDisable('Disabled', 0, 'not.be.checked');
    }
);

When('the user deletes the configured host group',
    () => {
        navigateAndSetOnChange();
        cy.getIframeBody().find('select').eq(0).select('Delete');
        cy.wait('@getTimeZone');
        cy.exportConfig();
    }
);

Then('the configured host group is not visible anymore on the host group page',
    () => {
        cy.getIframeBody().contains(hostGroup + '_1').should('not.exist');
    }
);