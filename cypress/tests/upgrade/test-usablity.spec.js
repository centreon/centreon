const { contains } = require("cypress/types/jquery");
const { it } = require("mocha");

describe("Test Upgrade Centreon", () => {

    context("Test some itens ", () => {

        before(() => {
            // Log in to Centreon
            cy.visit('/centreon/login');
            cy.getByLabel({ label: 'Alias', tag: 'input' }).type('admin');
            cy.getByLabel({ label: 'Password', tag: 'input' }).type('q1w2e3r4');
            cy.getByLabel({ label: 'Connect', tag: 'button' }).click();
        });

        // Test the MainPage
        it("Go to Main Page", () => {

            cy.url().should('include', '/monitoring/resources');
            cy.wait('@userTopCounterEndpoint');

        });

        // Menu navigation to hosts configuration
        it("Go to list hosts", () => {

            cy.get('.MuiButtonBase-root .MuiListItemButton-root:contains("Configuration")').click();
            cy.get('.MuiButtonBase-root .MuiListItemButton-root:contains("Hosts")').click();
            cy.get('.pathway').should('contain', 'Configuration').and('contain', 'Hosts');
        });

        // Menu navigation to hosts configuration
        it("Add a new host configuration", () => {

            cy.get('.btc a:contains("Add")').click();
            cy.get('.FormHeader').invoke('innerText').then((text) => {
                expect(text).to.equal('| Add a Host');
            });
            // Fill form
            cy.get('input[name="host_name"]').type('host-test-1');
            cy.get('input[name="host_alias"]').type('alias-host-test-1');
            cy.get('input[name="host_address"]').type('127.0.0.1');
            cy.get('input[name="host_snmp_community"]').type('public');
            cy.get('input[name="host_snmp_version"]').select('2c');
            cy.get('.add-new-entry').click();
            cy.get('#clone_template #tpSelect_0').select('generic-active-host-custom');
            cy.get('.add-new-entry').click();
            cy.get('#clone_template #tpSelect_1').select('OS-Linux-SNMP-custom');
            // save host information
            cy.get('input[name="submitC"]').click();

            // Check if exists the new host on the table
            cy.get('.ListTable td').contains('host-test-1');

        });

        it("Check services of host", () => {
            cy.get('.MuiButtonBase-root .MuiListItemButton-root:contains("Configuration")').click();
            cy.get('.MuiButtonBase-root .MuiListItemButton-root:contains("Services by host")').click();
            cy.get('.pathway').should('contain', 'Configuration').and('contain', 'Services').and('contain', 'Services by host');

            // Fill search with host
            cy.get('input[name="searchH"]').type('host-test-1');
            cy.get('input[name="Search"]').click();

            // Verify if exists 5 services for host
            cy.get('.ListTable tr:not(".ListHeader")').should('have.length.gte', 5);

        });

        it("Create a ACL", () => {
            cy.get('.MuiButtonBase-root .MuiListItemButton-root:contains("Administration")').click();
            cy.get('.MuiButtonBase-root .MuiListItemButton-root:contains("ACL")').click();
            cy.get('.MuiButtonBase-root .MuiListItemButton-root:contains("Menus Access")').click();
            cy.get('.pathway').should('contain', 'Administration').and('contain', 'ACL').and('contain', 'Menus Access');

            cy.get('a:contains("Add")').click();

            // Fill new ACL data
            cy.get('input[name="acl_topo_name"]').type('acl-test-1');
            cy.get('#acl_groups-f').select('ALL');
            cy.get('input[name="add"]').click();
            cy.get('#acl_groups-t').contains('ALL');
            cy.get('.FormTable td').find('input[type="checkbox"]').check();

            // save ACL
            cy.get('input[name="submitA"]').click();

            cy.get('.pathway').should('contain', 'Administration').and('contain', 'ACL').and('contain', 'Menus Access');
            cy.get('.ListTable td').contains('acl-test-1');
        });

        it("Create a new user", () => {
            cy.get('.MuiButtonBase-root .MuiListItemButton-root:contains("Configuration")').click();
            cy.get('.MuiButtonBase-root .MuiListItemButton-root:contains("Users")').click();
            cy.get('.MuiButtonBase-root .MuiListItemButton-root:contains("Contacts / Users")').click();
            cy.get('.pathway').should('contain', 'Configuration').and('contain', 'Users').and('contain', 'Contacts / Users');

            cy.get('a:contains("Add")').click();

            // Fill new user data
            cy.get('input[name="contact_alias"]').type('user-test-1');
            cy.get('input[name="contact_name"]').type('User Test');
            cy.get('input[name="contact_email"]').type('user@test.com');
            cy.get('input[name="contact_alias"]').type('user-test-1');

            // Save
            cy.get('input[name="submitA"]').click();

            cy.get('.pathway').should('contain', 'Configuration').and('contain', 'Users').and('contain', 'Contacts / Users');
            cy.get('.ListTable td').contains('user-test-1');
        });

    });
});
