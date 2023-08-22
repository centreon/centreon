/* eslint-disable @typescript-eslint/no-namespace */

const apiBase = '/centreon/api';
const apiActionV1 = `${apiBase}/index.php`;

interface SubmitResult {
  host: string;
  output: string;
  perfdata?: string | null;
  service: string;
  status: string;
}

const getStatusNumberFromString = (status: string): number => {
  const statuses = {
    critical: '2',
    down: '1',
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

Cypress.Commands.add(
  'submitResults',
  (results: Array<SubmitResult>): Cypress.Chainable => {
    results.forEach(({ host, output, perfdata = '', service, status }) => {
      const timestampNow = Math.floor(Date.now() / 1000) - 15;
      const updatetime = timestampNow.toString();

      cy.request({
        body: {
          results: [
            {
              host,
              output,
              perfdata,
              service,
              status: getStatusNumberFromString(status),
              updatetime
            }
          ]
        },
        headers: {
          'Content-Type': 'application/json',
          'centreon-auth-token': window.localStorage.getItem('userTokenApiV1')
        },
        method: 'POST',
        url: `${apiActionV1}?action=submit&object=centreon_submit_results`
      });
    });

    return cy.wrap(null);
  }
);

declare global {
  namespace Cypress {
    interface Chainable {
      submitResults: (props: Array<SubmitResult>) => Cypress.Chainable;
    }
  }
}

export {};
