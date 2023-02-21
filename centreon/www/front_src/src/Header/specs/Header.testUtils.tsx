import React from 'react';

import '@testing-library/cypress/add-commands';
import { BrowserRouter as Router } from 'react-router-dom';
import { mergeDeepRight } from 'ramda';

import { SnackbarProvider, Method, TestQueryProvider } from '@centreon/ui';

import Header from '../index';
import type {
  HostStatusResponse,
  ServiceStatusResponse
} from '../api/decoders';
import type { PollersIssuesList } from '../api/models';
import { retrievedNavigation } from '../../Navigation/mocks';
import type Navigation from '../../Navigation/models';

export type DeepPartial<Thing> = Thing extends Array<infer InferredArrayMember>
  ? DeepPartialArray<InferredArrayMember>
  : Thing extends object
  ? DeepPartialObject<Thing>
  : Thing | undefined;

type DeepPartialArray<Thing> = Array<DeepPartial<Thing>>;

type DeepPartialObject<Thing> = {
  [Key in keyof Thing]?: DeepPartial<Thing[Key]>;
};

const hostStatusStub: HostStatusResponse = {
  down: {
    total: '43',
    unhandled: '34'
  },
  ok: '12134',
  pending: '10',
  refreshTime: 1000000,
  time: 1252285255,
  total: 1605,
  unreachable: {
    total: '0',
    unhandled: '0'
  }
};

const serviceStatusStub: ServiceStatusResponse = {
  critical: {
    total: '43',
    unhandled: '34'
  },
  ok: '12134',
  pending: '1000000',
  refreshTime: 90,
  time: 1252285255,
  total: 1605,
  unknown: {
    total: '43',
    unhandled: '34'
  },
  warning: {
    total: '43',
    unhandled: '34'
  }
};

const userStub = {
  autologinkey: null,
  fullname: 'admin admin',
  hasAccessToProfile: true,
  isExportButtonEnabled: false,
  locale: 'en_US.UTF-8',
  password_remaining_time: 15369910,
  soundNotificationsEnabled: true,
  timezone: 'Europe/Paris',
  userId: '1',
  username: 'admin'
};

const pollersListIssuesStubs: PollersIssuesList = {
  issues: {
    database: {
      critical: {
        poller: {
          id: 125,
          name: 'poller 125',
          since: '24/12/2019'
        },
        total: 0
      },
      total: 0,
      warning: {
        poller: {
          id: 145,
          name: 'poller 145',
          since: '24/08/2022'
        },
        total: 0
      }
    },
    latency: {
      critical: {
        poller: {
          id: 175,
          name: 'poller 175',
          since: '24/07/2021'
        },
        total: 0
      },
      total: 0,
      warning: {
        poller: {
          id: 789,
          name: 'poller 789',
          since: '24/02/2023'
        },
        total: 0
      }
    }
  },
  refreshTime: 1000000,
  total: 12
};

export interface Stubs {
  hosts_status: HostStatusResponse;
  navigationList: Navigation;
  pollersListIssues: PollersIssuesList;
  servicesStatus: ServiceStatusResponse;
  user: typeof userStub;
}

type RequestHandler = (
  req: { url: { searchParams: { get: (param: string) => string } } },
  res: (response: string) => string,
  ctx: { json: (object: Record<string, unknown>) => string }
) => string | undefined;

const requestHandler =
  (stubs: DeepPartial<Stubs>): RequestHandler =>
  (req, res, ctx) => {
    const datas = mergeDeepRight(
      {
        hosts_status: hostStatusStub,
        navigationList: retrievedNavigation,
        pollersListIssues: pollersListIssuesStubs,
        servicesStatus: serviceStatusStub,
        user: userStub
      },
      stubs as Stubs
    );

    const object = req.url.searchParams.get('object');
    const action = req.url.searchParams.get('action');

    if (object === 'centreon_topcounter' || object === 'centreon_topology') {
      return res(ctx.json(datas[action]));
    }

    return undefined;
  };

export const initialize = (stubs: DeepPartial<Stubs> = {}): void => {
  cy.interceptRequest(
    Method.GET,
    '**/internal.php',
    requestHandler(stubs),
    'APIV1'
  );

  cy.interceptRequest(
    Method.GET,
    'api/latest/configuration/monitoring-servers/generate-and-reload',
    (_, res, ctx) => res(ctx.json({})),
    'generateConfigAndReload'
  );

  cy.clock(new Date(2022, 3, 28, 16, 20, 0), ['Date']);

  cy.mount({
    Component: (
      <SnackbarProvider maxSnackbars={2}>
        <TestQueryProvider>
          <Router>
            <Header />
          </Router>
        </TestQueryProvider>
      </SnackbarProvider>
    )
  });

  cy.viewport(1200, 300);
};
