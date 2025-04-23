import { renderHook } from '@testing-library/react';
import dayjs from 'dayjs';

import {
  getFetchCall,
  mockResponse,
  resetMocks,
  waitFor
} from '../../test/testRenderer';
import TestQueryProvider from '../api/TestQueryProvider';

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
  resetMocks();
  mockResponse({
    data: getMockedResponse(expirationDate)
  });
};

const showMessage = jest.fn();

jest.mock('../Snackbar/useSnackbar', () => ({
  __esModule: true,
  default: jest
    .fn()
    .mockImplementation(() => ({ showWarningMessage: showMessage }))
}));

const initialize = (): void => {
  renderHook(
    () =>
      useLicenseExpirationWarning({
        module: 'centreon-autodiscovery-server'
      }),
    {
      wrapper: TestQueryProvider
    }
  );
};

const currentDate = dayjs();

describe('License', () => {
  it('does not display any warning message when the license expires in more than 15 days from the current date', () => {
    mockRequest({ expirationDate: currentDate.add(20, 'day') });
    initialize();

    waitFor(() => {
      expect(getFetchCall(0)).toEqual(
        'internal.php?object=centreon_module&action=list'
      );
    });

    expect(showMessage).not.toHaveBeenCalled();
  });

  it('displays a warning message when the license expires within 15 days', () => {
    mockRequest({ expirationDate: currentDate.add(10.5, 'day') });
    initialize();

    waitFor(() => {
      expect(getFetchCall(0)).toEqual(
        'internal.php?object=centreon_module&action=list'
      );
    });

    waitFor(() => {
      expect(showMessage).toHaveBeenCalledWith(
        labelLicenseWarning('centreon-autodiscovery-server', 10)
      );
    });
  });
});
