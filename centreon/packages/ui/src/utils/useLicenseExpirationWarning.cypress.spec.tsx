import dayjs from 'dayjs';

import { TestQueryProvider, Method, SnackbarProvider } from '@centreon/ui';

import { labelLicenseWarning } from './translatedLabel';

import { useLicenseExpirationWarning } from '.';

const getMockedResponse = (expirationDate): object => ({
  result: {
    module: {
      entities: [
        {
          description: 'Centreon Auto Discovery',
          id: 'centreon-autodiscovery-server',
          label: 'Centreon',
          license: {
            expiration_date: expirationDate,
            host_limit: -1,
            host_usage: 1,
            is_valid: true,
            required: true
          },
          type: 'module',
          version: {
            available: '23.10.0',
            current: '23.10.0',
            installed: true,
            outdated: false
          }
        },
        {
          description: 'Centreon License Manager',
          id: 'centreon-license-manager',
          label: 'Centreon',
          license: {
            required: false
          },
          type: 'module',
          version: {
            available: '23.10.0',
            current: '23.10.0',
            installed: true,
            outdated: false
          }
        },
        {
          description: 'Centreon Monitoring Connectors Manager',
          id: 'centreon-pp-manager',
          label: 'Centreon',
          license: {
            required: false
          },
          type: 'module',
          version: {
            available: '23.10.0',
            current: '23.10.0',
            installed: true,
            outdated: false
          }
        }
      ],
      pagination: {
        limit: 3,
        offset: 0,
        total: 3
      }
    },
    widget: {
      entities: [],
      pagination: {
        limit: 0,
        offset: 0,
        total: 0
      }
    }
  },
  status: true
});

const mockRequest = ({ expirationDate }: { expirationDate }): void => {
  cy.interceptAPIRequest({
    alias: 'getLicenseInformations',
    method: Method.GET,
    path: '**internal.php?object=centreon_module&action=list',
    response: getMockedResponse(expirationDate)
  });
};

const TestComponent = (): JSX.Element => {
  useLicenseExpirationWarning({
    module: 'centreon-autodiscovery-server'
  });

  return <div />;
};

const TestWithQueryProvider = (): JSX.Element => {
  return (
    <TestQueryProvider>
      <SnackbarProvider>
        <TestComponent />
      </SnackbarProvider>
    </TestQueryProvider>
  );
};

const initialize = (): void => {
  cy.viewport('macbook-13');

  cy.mount({
    Component: <TestWithQueryProvider />
  });
};

const currentDate = dayjs();

describe('License', () => {
  beforeEach(initialize);

  it('does not display any warning message when the license expires in more than 15 days from the current date', () => {
    mockRequest({ expirationDate: currentDate.add(20, 'day') });

    cy.waitForRequest('@getLicenseInformations');

    cy.findByText(
      labelLicenseWarning('centreon-autodiscovery-server', 20)
    ).should('not.exist');
  });
  it('displays a warning message when the license expires within 15 days', () => {
    mockRequest({ expirationDate: currentDate.add(10.5, 'day') });

    cy.findByText(labelLicenseWarning('centreon-autodiscovery-server', 10));

    cy.makeSnapshot();
  });
});
