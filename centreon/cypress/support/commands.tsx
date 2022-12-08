/* eslint-disable @typescript-eslint/no-namespace */

import { addMatchImageSnapshotCommand } from 'cypress-image-snapshot/command';
import '@testing-library/cypress/add-commands';
import 'cypress-msw-interceptor';

addMatchImageSnapshotCommand({
  capture: 'viewport',
  customDiffConfig: { threshold: 0.1 },
  customSnapshotsDir: './cypress/visual-testing-snapshots',
  failureThreshold: 0.03,
  failureThresholdType: 'percent'
});

declare global {
  namespace Cypress {
    interface Chainable {
      interceptAPIRequest: (
        props: InterceptAPIRequestProps
      ) => Cypress.Chainable;
      interceptRequest: (method, path, mock, alias) => Cypress.Chainable;
      waitForRequest: (alias) => Cypress.Chainable;
    }
  }
}

export enum Method {
  DELETE = 'DELETE',
  GET = 'GET',
  PATCH = 'PATCH',
  POST = 'POST',
  PUT = 'PUT'
}

export interface InterceptAPIRequestProps {
  alias: string;
  method: Method;
  path: string;
  response: object | Array<object>;
  statusCode?: number;
}

Cypress.Commands.add(
  'interceptAPIRequest',
  ({
    method,
    path,
    response,
    alias,
    statusCode = 200
  }: InterceptAPIRequestProps): void => {
    cy.interceptRequest(
      method,
      path,
      async (req, res, ctx) => {
        return res(ctx.delay(500), ctx.json(response), ctx.status(statusCode));
      },
      alias
    );
  }
);
