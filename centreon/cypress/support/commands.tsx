/* eslint-disable @typescript-eslint/no-namespace */
import React from 'react';

import { mount } from 'cypress/react18';

import '@testing-library/cypress/add-commands';
import 'cypress-msw-interceptor';
import 'cypress-plugin-tab';

import { ThemeProvider } from '@centreon/ui';

window.React = React;

Cypress.Commands.add('mount', ({ Component, options }) => {
  const wrapped = <ThemeProvider>{Component}</ThemeProvider>;

  return mount(wrapped, options);
});

Cypress.Commands.add('displayFilterMenu', () => {
  cy.get('[aria-label="Filter options"]').click();

  cy.contains('Type').should('be.visible').click();
});

Cypress.Commands.add('clickOutside', () => {
  cy.get('body').click(0, 0);
});

interface MountProps {
  Component: React.Element;
  options?: object;
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
  response?: object | Array<object>;
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

Cypress.Commands.add('waitFiltersAndListingRequests', () => {
  cy.waitForRequest('@filterRequest');
  cy.waitForRequest('@dataToListingTable');
});

declare global {
  namespace Cypress {
    interface Chainable {
      interceptAPIRequest: (
        props: InterceptAPIRequestProps
      ) => Cypress.Chainable;
      interceptRequest: (method, path, mock, alias) => Cypress.Chainable;
      mount: ({ Component, options = {} }: MountProps) => Cypress.Chainable;
      waitFiltersAndListingRequests: () => Cypress.Chainable;
      waitForRequest: (alias) => Cypress.Chainable;
    }
  }
}
