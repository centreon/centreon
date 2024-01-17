"use strict";
/* eslint-disable @typescript-eslint/no-namespace */
var __assign = (this && this.__assign) || function () {
    __assign = Object.assign || function(t) {
        for (var s, i = 1, n = arguments.length; i < n; i++) {
            s = arguments[i];
            for (var p in s) if (Object.prototype.hasOwnProperty.call(s, p))
                t[p] = s[p];
        }
        return t;
    };
    return __assign.apply(this, arguments);
};
Object.defineProperty(exports, "__esModule", { value: true });
require("./commands/configuration");
require("./commands/monitoring");
var installLogsCollector_1 = require("cypress-terminal-report/src/installLogsCollector");
(0, installLogsCollector_1.default)({ enableExtendedCollector: true });
var apiLoginV2 = '/centreon/authentication/providers/configurations/local';
var artifactIllegalCharactersMatcher = /[,\s/|<>*?:"]/g;
Cypress.Commands.add('getWebVersion', function () {
    return cy
        .exec("bash -c \"grep version ../../www/install/insertBaseConf.sql | cut -d \\' -f 4 | awk 'NR==2'\"")
        .then(function (_a) {
        var stdout = _a.stdout;
        var found = stdout.match(/(\d+\.\d+)\.(\d+)/);
        if (found) {
            return cy.wrap({ major_version: found[1], minor_version: found[2] });
        }
        throw new Error('Current web version cannot be parsed.');
    });
});
Cypress.Commands.add('getIframeBody', function () {
    return cy
        .get('iframe#main-content', { timeout: 10000 })
        .its('0.contentDocument.body')
        .should('not.be.empty')
        .then(cy.wrap);
});
Cypress.Commands.add('hoverRootMenuItem', function (rootItemNumber) {
    return cy
        .get('div[data-testid="sidebar"] li')
        .eq(rootItemNumber)
        .trigger('mouseover');
});
Cypress.Commands.add('clickSubRootMenuItem', function (page) {
    return cy.get('div[data-cy="collapse"]').eq(1).contains(page).click();
});
Cypress.Commands.add('navigateTo', function (_a) {
    var rootItemNumber = _a.rootItemNumber, subMenu = _a.subMenu, page = _a.page;
    if (subMenu) {
        cy.hoverRootMenuItem(rootItemNumber)
            .contains(subMenu)
            .trigger('mouseover')
            .get('.MuiCollapse-wrapper')
            .find('div[data-cy="collapse"]')
            .should('be.visible')
            .and('contain', page);
        cy.clickSubRootMenuItem(page);
        return;
    }
    cy.hoverRootMenuItem(rootItemNumber).contains(page).click({ force: true });
});
Cypress.Commands.add('moveSortableElement', {
    prevSubject: 'element'
}, function (subject, direction) {
    var key = "{".concat(direction, "arrow}");
    cy.wrap(subject)
        .type(' ', {
        force: true,
        scrollBehavior: false
    })
        .closest('body')
        .type(key, {
        scrollBehavior: false
    })
        .type(' ', {
        scrollBehavior: false
    });
});
Cypress.Commands.add('getContainerId', function (containerName) {
    cy.log("Getting container id of ".concat(containerName));
    return cy.task('getContainerId', containerName);
});
Cypress.Commands.add('getContainerIpAddress', function (containerName) {
    cy.log("Getting container ip address of ".concat(containerName));
    return cy.task('getContainerIpAddress', containerName);
});
Cypress.Commands.add('getContainersLogs', function () {
    cy.log('Getting containers logs');
    return cy.task('getContainersLogs');
});
Cypress.Commands.add('copyFromContainer', function (_a) {
    var _b = _a.name, name = _b === void 0 ? 'web' : _b, source = _a.source, destination = _a.destination;
    cy.log("Copy content from ".concat(name, ":").concat(source, " to ").concat(destination));
    return cy.task('copyFromContainer', {
        destination: destination,
        serviceName: name,
        source: source
    });
});
Cypress.Commands.add('copyToContainer', function (_a, options) {
    var _b = _a.name, name = _b === void 0 ? 'web' : _b, source = _a.source, destination = _a.destination;
    cy.log("Copy content from ".concat(source, " to ").concat(name, ":").concat(destination));
    return cy.exec("docker cp ".concat(source, " ").concat(name, ":").concat(destination), options);
});
Cypress.Commands.add('loginByTypeOfUser', function (_a) {
    var _b = _a.jsonName, jsonName = _b === void 0 ? 'admin' : _b, _c = _a.loginViaApi, loginViaApi = _c === void 0 ? false : _c;
    if (loginViaApi) {
        return cy
            .fixture("users/".concat(jsonName, ".json"))
            .then(function (user) {
            return cy.request({
                body: {
                    login: user.login,
                    password: user.password
                },
                method: 'POST',
                url: apiLoginV2
            });
        })
            .visit("".concat(Cypress.config().baseUrl))
            .wait('@getNavigationList');
    }
    cy.visit("".concat(Cypress.config().baseUrl))
        .fixture("users/".concat(jsonName, ".json"))
        .then(function (credential) {
        cy.getByLabel({ label: 'Alias', tag: 'input' }).type("{selectAll}{backspace}".concat(credential.login));
        cy.getByLabel({ label: 'Password', tag: 'input' }).type("{selectAll}{backspace}".concat(credential.password));
    })
        .getByLabel({ label: 'Connect', tag: 'button' })
        .click();
    return cy.get('.MuiAlert-message').then(function ($snackbar) {
        if ($snackbar.text().includes('Login succeeded')) {
            cy.wait('@getNavigationList');
        }
    });
});
Cypress.Commands.add('visitEmptyPage', function () {
    return cy
        .intercept('/waiting-page', {
        headers: { 'content-type': 'text/html' },
        statusCode: 200
    })
        .visit('/waiting-page');
});
Cypress.Commands.add('waitForContainerAndSetToken', function () {
    return cy.setUserTokenApiV1();
});
Cypress.Commands.add('execInContainer', function (_a) {
    var command = _a.command, name = _a.name;
    var commands = typeof command === 'string' || command instanceof String
        ? [command]
        : command;
    var results = commands.reduce(function (acc, runCommand) {
        cy.task('execInContainer', { command: runCommand, name: name }, { timeout: 600000 }).then(function (result) {
            if (result.exitCode) {
                // output will not be truncated
                throw new Error("\nExecution of \"".concat(runCommand, "\" failed\nExit code: ").concat(result.exitCode, "\nOutput:\n").concat(result.output));
            }
            acc.output = "".concat(acc.output).concat(result.output);
        });
        return acc;
    }, { exitCode: 0, output: '' });
    return cy.wrap(results);
});
Cypress.Commands.add('requestOnDatabase', function (_a) {
    var database = _a.database, query = _a.query;
    return cy.task('requestOnDatabase', { database: database, query: query });
});
Cypress.Commands.add('startContainer', function (_a) {
    var command = _a.command, name = _a.name, image = _a.image, portBindings = _a.portBindings;
    cy.log("Starting container ".concat(name, " from image ").concat(image));
    return cy.task('startContainer', { command: command, image: image, name: name, portBindings: portBindings }, { timeout: 600000 } // 10 minutes because docker pull can be very slow
    );
});
Cypress.Commands.add('startContainers', function (_a) {
    var _b = _a === void 0 ? {} : _a, _c = _b.databaseImage, databaseImage = _c === void 0 ? Cypress.env('DATABASE_IMAGE') : _c, _d = _b.openidImage, openidImage = _d === void 0 ? "docker.centreon.com/centreon/keycloak:".concat(Cypress.env('OPENID_IMAGE_VERSION')) : _d, _e = _b.profiles, profiles = _e === void 0 ? [] : _e, _f = _b.samlImage, samlImage = _f === void 0 ? "docker.centreon.com/centreon/keycloak:".concat(Cypress.env('SAML_IMAGE_VERSION')) : _f, _g = _b.useSlim, useSlim = _g === void 0 ? true : _g, _h = _b.webOs, webOs = _h === void 0 ? Cypress.env('WEB_IMAGE_OS') : _h, _j = _b.webVersion, webVersion = _j === void 0 ? Cypress.env('WEB_IMAGE_VERSION') : _j;
    cy.log('Starting containers ...');
    var slimSuffix = useSlim ? '-slim' : '';
    var webImage = "docker.centreon.com/centreon/centreon-web".concat(slimSuffix, "-").concat(webOs, ":").concat(webVersion);
    return cy
        .task('startContainers', { databaseImage: databaseImage, openidImage: openidImage, profiles: profiles, samlImage: samlImage, webImage: webImage }, { timeout: 600000 } // 10 minutes because docker pull can be very slow
    )
        .then(function () {
        var baseUrl = 'http://127.0.0.1:4000';
        Cypress.config('baseUrl', baseUrl);
        return cy.wrap(null);
    })
        .visit('/') // this is necessary to refresh browser cause baseUrl has changed (flash appears in video)
        .setUserTokenApiV1();
});
Cypress.Commands.add('stopContainer', function (_a) {
    var name = _a.name;
    cy.log("Stopping container ".concat(name));
    return cy.task('stopContainer', { name: name });
});
Cypress.Commands.add('stopContainers', function () {
    cy.log('Stopping containers ...');
    var logDirectory = "results/logs/".concat(Cypress.spec.name.replace(artifactIllegalCharactersMatcher, '_'), "/").concat(Cypress.currentTest.title.replace(artifactIllegalCharactersMatcher, '_'));
    var name = 'web';
    return cy
        .visitEmptyPage()
        .createDirectory(logDirectory)
        .getContainersLogs()
        .then(function (containersLogs) {
        Object.entries(containersLogs).forEach(function (_a) {
            var containerName = _a[0], logs = _a[1];
            cy.writeFile("results/logs/".concat(Cypress.spec.name.replace(artifactIllegalCharactersMatcher, '_'), "/").concat(Cypress.currentTest.title.replace(artifactIllegalCharactersMatcher, '_'), "/container-").concat(containerName, ".log"), logs);
        });
    })
        .copyFromContainer({
        destination: "".concat(logDirectory, "/broker"),
        name: name,
        source: '/var/log/centreon-broker'
    })
        .copyFromContainer({
        destination: "".concat(logDirectory, "/engine"),
        name: name,
        source: '/var/log/centreon-engine'
    })
        .copyFromContainer({
        destination: "".concat(logDirectory, "/centreon"),
        name: name,
        source: '/var/log/centreon'
    })
        .copyFromContainer({
        destination: "".concat(logDirectory, "/centreon-gorgone"),
        name: name,
        source: '/var/log/centreon-gorgone'
    })
        .then(function () {
        if (Cypress.env('WEB_IMAGE_OS').includes('alma')) {
            return cy.copyFromContainer({
                destination: "".concat(logDirectory, "/php"),
                name: name,
                source: '/var/log/php-fpm'
            });
        }
        return cy.copyFromContainer({
            destination: "".concat(logDirectory, "/php8.1-fpm-centreon-error.log"),
            name: name,
            source: '/var/log/php8.1-fpm-centreon-error.log'
        }, { failOnNonZeroExit: false });
    })
        .then(function () {
        if (Cypress.env('WEB_IMAGE_OS').includes('alma')) {
            return cy.copyFromContainer({
                destination: "".concat(logDirectory, "/httpd"),
                name: name,
                source: '/var/log/httpd'
            });
        }
        return cy.copyFromContainer({
            destination: "".concat(logDirectory, "/apache2"),
            name: name,
            source: '/var/log/apache2'
        }, { failOnNonZeroExit: false });
    })
        .exec("chmod -R 755 \"".concat(logDirectory, "\""))
        .task('stopContainers', {}, { timeout: 600000 } // 10 minutes because docker pull can be very slow
    );
});
Cypress.Commands.add('createDirectory', function (directoryPath) {
    return cy.task('createDirectory', directoryPath);
});
Cypress.Commands.add('insertDashboardList', function (fixtureFile) {
    return cy.fixture(fixtureFile).then(function (dashboardList) {
        cy.wrap(Promise.all(dashboardList.map(function (dashboardBody) {
            return cy.insertDashboard(__assign({}, dashboardBody));
        })));
    });
});
Cypress.Commands.add('insertDashboard', function (dashboardBody) {
    return cy.request({
        body: __assign({}, dashboardBody),
        method: 'POST',
        url: '/centreon/api/latest/configuration/dashboards'
    });
});
Cypress.Commands.add('insertDashboardWithWidget', function (dashboardBody, patchBody) {
    cy.request({
        body: __assign({}, dashboardBody),
        method: 'POST',
        url: '/centreon/api/latest/configuration/dashboards'
    }).then(function (response) {
        var dashboardId = response.body.id;
        cy.waitUntil(function () {
            return cy
                .request({
                method: 'GET',
                url: "/centreon/api/latest/configuration/dashboards/".concat(dashboardId)
            })
                .then(function (getResponse) {
                return getResponse.body && getResponse.body.id === dashboardId;
            });
        }, {
            timeout: 10000
        });
        cy.request({
            body: patchBody,
            method: 'PATCH',
            url: "/centreon/api/latest/configuration/dashboards/".concat(dashboardId)
        });
    });
});
Cypress.Commands.add('shareDashboardToUser', function (_a) {
    var dashboardName = _a.dashboardName, userName = _a.userName, role = _a.role;
    Promise.all([
        cy.request({
            method: 'GET',
            url: "/centreon/api/latest/configuration/users?search={\"name\":\"".concat(userName, "\"}")
        }),
        cy.request({
            method: 'GET',
            url: "/centreon/api/latest/configuration/dashboards?search={\"name\":\"".concat(dashboardName, "\"}")
        })
    ]).then(function (_a) {
        var retrievedUser = _a[0], retrievedDashboard = _a[1];
        var userId = retrievedUser.body.result[0].id;
        var dashboardId = retrievedDashboard.body.result[0].id;
        cy.request({
            body: {
                id: userId,
                role: "".concat(role)
            },
            method: 'POST',
            url: "/centreon/api/latest/configuration/dashboards/".concat(dashboardId, "/access_rights/contacts")
        });
    });
});
Cypress.Commands.add('getTimeFromHeader', function () {
    return cy
        .get('header div[data-cy="clock"]', { timeout: 10000 })
        .should('be.visible')
        .then(function ($time) {
        var headerTime = $time.children()[1].textContent;
        if (headerTime === null || headerTime === void 0 ? void 0 : headerTime.match(/\d+:\d+/)) {
            cy.log("header time is : ".concat(headerTime));
            return cy.wrap(headerTime);
        }
        throw new Error("header time is not displayed");
    });
});
