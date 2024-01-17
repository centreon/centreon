"use strict";
/* eslint-disable @typescript-eslint/no-namespace */
Object.defineProperty(exports, "__esModule", { value: true });
var apiBase = '/centreon/api';
var apiActionV1 = "".concat(apiBase, "/index.php");
Cypress.Commands.add('executeActionViaClapi', function (_a) {
    var bodyContent = _a.bodyContent, _b = _a.method, method = _b === void 0 ? 'POST' : _b;
    return cy.request({
        body: bodyContent,
        headers: {
            'Content-Type': 'application/json',
            'centreon-auth-token': window.localStorage.getItem('userTokenApiV1')
        },
        method: method,
        url: "".concat(apiActionV1, "?action=action&object=centreon_clapi")
    });
});
Cypress.Commands.add('executeCommandsViaClapi', function (fixtureFile) {
    return cy.fixture(fixtureFile).then(function (listRequestConfig) {
        cy.wrap(Promise.all(listRequestConfig.map(function (request) {
            return cy.executeActionViaClapi({ bodyContent: request });
        })));
    });
});
var defaultDayPeriod = '00:00-24:00';
Cypress.Commands.add('addTimePeriod', function (_a) {
    var _b = _a.alias, alias = _b === void 0 ? null : _b, _c = _a.friday, friday = _c === void 0 ? defaultDayPeriod : _c, _d = _a.monday, monday = _d === void 0 ? defaultDayPeriod : _d, name = _a.name, _e = _a.saturday, saturday = _e === void 0 ? defaultDayPeriod : _e, _f = _a.sunday, sunday = _f === void 0 ? defaultDayPeriod : _f, _g = _a.thursday, thursday = _g === void 0 ? defaultDayPeriod : _g, _h = _a.tuesday, tuesday = _h === void 0 ? defaultDayPeriod : _h, _j = _a.wednesday, wednesday = _j === void 0 ? defaultDayPeriod : _j;
    var timePeriodAlias = alias === null ? name : alias;
    return cy
        .executeActionViaClapi({
        bodyContent: {
            action: 'ADD',
            object: 'TP',
            values: "".concat(name, ";").concat(timePeriodAlias)
        }
    })
        .then(function () {
        var weekDays = {
            friday: friday,
            monday: monday,
            saturday: saturday,
            sunday: sunday,
            thursday: thursday,
            tuesday: tuesday,
            wednesday: wednesday
        };
        Object.entries(weekDays).map(function (_a) {
            var dayName = _a[0], dayValue = _a[1];
            return cy.executeActionViaClapi({
                bodyContent: {
                    action: 'SETPARAM',
                    object: 'TP',
                    values: "".concat(name, ";").concat(dayName, ";").concat(dayValue)
                }
            });
        });
        return cy.wrap(null);
    });
});
Cypress.Commands.add('addCheckCommand', function (_a) {
    var name = _a.name, _b = _a.enableShell, enableShell = _b === void 0 ? true : _b, command = _a.command;
    var commandEnableShell = enableShell ? 1 : 0;
    return cy
        .executeActionViaClapi({
        bodyContent: {
            action: 'ADD',
            object: 'CMD',
            values: "".concat(name, ";check;").concat(command)
        }
    })
        .executeActionViaClapi({
        bodyContent: {
            action: 'SETPARAM',
            object: 'CMD',
            values: "".concat(name, ";enable_shell;").concat(commandEnableShell)
        }
    });
});
Cypress.Commands.add('addHost', function (_a) {
    var _b = _a.activeCheckEnabled, activeCheckEnabled = _b === void 0 ? true : _b, _c = _a.address, address = _c === void 0 ? '127.0.0.1' : _c, _d = _a.alias, alias = _d === void 0 ? null : _d, _e = _a.checkCommand, checkCommand = _e === void 0 ? null : _e, _f = _a.checkPeriod, checkPeriod = _f === void 0 ? null : _f, _g = _a.hostGroup, hostGroup = _g === void 0 ? '' : _g, _h = _a.maxCheckAttempts, maxCheckAttempts = _h === void 0 ? 1 : _h, name = _a.name, _j = _a.passiveCheckEnabled, passiveCheckEnabled = _j === void 0 ? true : _j, _k = _a.poller, poller = _k === void 0 ? 'Central' : _k, _l = _a.template, template = _l === void 0 ? '' : _l;
    var hostAlias = alias === null ? name : alias;
    var hostMaxCheckAttempts = maxCheckAttempts === null ? '' : maxCheckAttempts;
    var hostActiveCheckEnabled = activeCheckEnabled ? 1 : 0;
    var hostPassiveCheckEnabled = passiveCheckEnabled ? 1 : 0;
    return cy
        .executeActionViaClapi({
        bodyContent: {
            action: 'ADD',
            object: 'HOST',
            values: "".concat(name, ";").concat(hostAlias, ";").concat(address, ";").concat(template, ";").concat(poller, ";").concat(hostGroup)
        }
    })
        .then(function () {
        var hostParams = {
            active_checks_enabled: hostActiveCheckEnabled,
            check_command: checkCommand,
            check_period: checkPeriod,
            max_check_attempts: hostMaxCheckAttempts,
            passive_checks_enabled: hostPassiveCheckEnabled
        };
        Object.entries(hostParams).map(function (_a) {
            var paramName = _a[0], paramValue = _a[1];
            if (paramValue === null) {
                return null;
            }
            return cy.executeActionViaClapi({
                bodyContent: {
                    action: 'SETPARAM',
                    object: 'HOST',
                    values: "".concat(name, ";").concat(paramName, ";").concat(paramValue)
                }
            });
        });
        return cy.wrap(null);
    });
});
Cypress.Commands.add('addServiceTemplate', function (_a) {
    var _b = _a.activeCheckEnabled, activeCheckEnabled = _b === void 0 ? true : _b, _c = _a.checkCommand, checkCommand = _c === void 0 ? null : _c, _d = _a.checkPeriod, checkPeriod = _d === void 0 ? null : _d, _e = _a.description, description = _e === void 0 ? null : _e, _f = _a.maxCheckAttempts, maxCheckAttempts = _f === void 0 ? 1 : _f, name = _a.name, _g = _a.passiveCheckEnabled, passiveCheckEnabled = _g === void 0 ? true : _g, _h = _a.template, template = _h === void 0 ? '' : _h;
    var serviceDescription = description === null ? name : description;
    var serviceMaxCheckAttempts = maxCheckAttempts === null ? '' : maxCheckAttempts;
    var serviceActiveCheckEnabled = activeCheckEnabled ? 1 : 0;
    var servicePassiveCheckEnabled = passiveCheckEnabled ? 1 : 0;
    return cy
        .executeActionViaClapi({
        bodyContent: {
            action: 'ADD',
            object: 'STPL',
            values: "".concat(name, ";").concat(description, ";").concat(template)
        }
    })
        .then(function () {
        var serviceParams = {
            active_checks_enabled: serviceActiveCheckEnabled,
            check_command: checkCommand,
            check_period: checkPeriod,
            description: serviceDescription,
            max_check_attempts: serviceMaxCheckAttempts,
            passive_checks_enabled: servicePassiveCheckEnabled
        };
        Object.entries(serviceParams).map(function (_a) {
            var paramName = _a[0], paramValue = _a[1];
            if (paramValue === null) {
                return null;
            }
            return cy.executeActionViaClapi({
                bodyContent: {
                    action: 'SETPARAM',
                    object: 'STPL',
                    values: "".concat(name, ";").concat(paramName, ";").concat(paramValue)
                }
            });
        });
        return cy.wrap(null);
    });
});
Cypress.Commands.add('addService', function (_a) {
    var _b = _a.activeCheckEnabled, activeCheckEnabled = _b === void 0 ? true : _b, _c = _a.checkCommand, checkCommand = _c === void 0 ? null : _c, _d = _a.checkPeriod, checkPeriod = _d === void 0 ? null : _d, host = _a.host, _e = _a.maxCheckAttempts, maxCheckAttempts = _e === void 0 ? 1 : _e, name = _a.name, _f = _a.passiveCheckEnabled, passiveCheckEnabled = _f === void 0 ? true : _f, _g = _a.template, template = _g === void 0 ? '' : _g;
    var serviceMaxCheckAttempts = maxCheckAttempts === null ? '' : maxCheckAttempts;
    var serviceActiveCheckEnabled = activeCheckEnabled ? 1 : 0;
    var servicePassiveCheckEnabled = passiveCheckEnabled ? 1 : 0;
    return cy
        .executeActionViaClapi({
        bodyContent: {
            action: 'ADD',
            object: 'SERVICE',
            values: "".concat(host, ";").concat(name, ";").concat(template)
        }
    })
        .then(function () {
        var serviceParams = {
            active_checks_enabled: serviceActiveCheckEnabled,
            check_command: checkCommand,
            check_period: checkPeriod,
            max_check_attempts: serviceMaxCheckAttempts,
            passive_checks_enabled: servicePassiveCheckEnabled
        };
        Object.entries(serviceParams).map(function (_a) {
            var paramName = _a[0], paramValue = _a[1];
            if (paramValue === null) {
                return null;
            }
            return cy.executeActionViaClapi({
                bodyContent: {
                    action: 'SETPARAM',
                    object: 'SERVICE',
                    values: "".concat(host, ";").concat(name, ";").concat(paramName, ";").concat(paramValue)
                }
            });
        });
        return cy.wrap(null);
    });
});
Cypress.Commands.add('applyPollerConfiguration', function (pollerName) {
    if (pollerName === void 0) { pollerName = 'Central'; }
    return cy.executeActionViaClapi({
        bodyContent: {
            action: 'APPLYCFG',
            values: pollerName
        }
    });
});
