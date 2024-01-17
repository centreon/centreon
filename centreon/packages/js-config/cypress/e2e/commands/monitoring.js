"use strict";
/* eslint-disable @typescript-eslint/no-namespace */
Object.defineProperty(exports, "__esModule", { value: true });
var apiBase = '/centreon/api';
var apiActionV1 = "".concat(apiBase, "/index.php");
var getStatusNumberFromString = function (status) {
    var statuses = {
        critical: '2',
        down: '1',
        ok: '0',
        unknown: '3',
        unreachable: '2',
        up: '0',
        warning: '1'
    };
    if (status in statuses) {
        return statuses[status];
    }
    throw new Error("Status ".concat(status, " does not exist"));
};
Cypress.Commands.add('submitResults', function (results) {
    results.forEach(function (_a) {
        var host = _a.host, output = _a.output, _b = _a.perfdata, perfdata = _b === void 0 ? '' : _b, _c = _a.service, service = _c === void 0 ? null : _c, status = _a.status;
        var timestampNow = Math.floor(Date.now() / 1000) - 15;
        var updatetime = timestampNow.toString();
        var result = {
            host: host,
            output: output,
            perfdata: perfdata,
            service: service,
            status: getStatusNumberFromString(status),
            updatetime: updatetime
        };
        cy.request({
            body: {
                results: [result]
            },
            headers: {
                'Content-Type': 'application/json',
                'centreon-auth-token': window.localStorage.getItem('userTokenApiV1')
            },
            method: 'POST',
            url: "".concat(apiActionV1, "?action=submit&object=centreon_submit_results")
        });
    });
    return cy.wrap(null);
});
