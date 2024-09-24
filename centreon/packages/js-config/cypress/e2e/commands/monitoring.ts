/* eslint-disable @typescript-eslint/no-namespace */

const apiBase = '/centreon/api';
const apiActionV1 = `${apiBase}/index.php`;

const getStatusNumberFromString = (status: string): number => {
  const statuses = {
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

  throw new Error(`Status ${status} does not exist`);
};

interface SubmitResult {
  host: string;
  output: string;
  perfdata?: string | null;
  service?: string | null;
  status: string;
}

Cypress.Commands.add(
  'submitResults',
  (results: Array<SubmitResult>): Cypress.Chainable => {
    results.forEach(
      ({ host, output, perfdata = '', service = null, status }) => {
        const timestampNow = Math.floor(Date.now() / 1000) - 15;
        const updatetime = timestampNow.toString();

        const result = {
          host,
          output,
          perfdata,
          service,
          status: getStatusNumberFromString(status),
          updatetime
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
          url: `${apiActionV1}?action=submit&object=centreon_submit_results`
        });
      }
    );

    return cy.wrap(null);
  }
);

Cypress.Commands.add(
  'scheduleServiceCheck',
  ({
    host,
    isForced = true,
    service
  }: ServiceCheck): Cypress.Chainable => {
    let query = `SELECT parent_id, id FROM resources WHERE parent_name = '${host}' AND name = '${service}'`;

    return cy
      .requestOnDatabase({
        database: 'centreon_storage',
        query
      })
      .then(([rows]) => {
        if (rows.length === 0) {
          throw new Error(`Cannot find service ${host} / ${service}`);
        }

        const hostId = rows[0].parent_id;
        const serviceId = rows[0].id;

        return cy.request({
          body: {
            check: {
              is_forced: isForced
            },
            resources: [
              {
                id: serviceId,
                parent: {
                  id: hostId
                },
                type: 'service'
              }
            ]
          },
          method: 'POST',
          timeout: 30000,
          url: '/centreon/api/latest/monitoring/resources/check'
        }).then((response) => {
          expect(response.status).to.eq(204);

          return cy.wrap(null);
        });
      });
  }
);

declare global {
  namespace Cypress {
    interface Chainable {
      submitResults: (props: Array<SubmitResult>) => Cypress.Chainable;
      scheduleServiceCheck: (serviceCheck) => Cypress.Chainable;
    }
  }
}

export {};
