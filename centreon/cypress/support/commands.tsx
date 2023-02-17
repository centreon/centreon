/* eslint-disable @typescript-eslint/no-namespace */
import React from 'react';

import { mount } from 'cypress/react18';

import '@testing-library/cypress/add-commands';
import 'cypress-msw-interceptor';

import { ThemeProvider } from '@centreon/ui';

window.React = React;

Cypress.Commands.add('mount', ({ Component, options }) => {
  const wrapped = (
    <ThemeProvider>
      <div style={{ backgroundColor: '#fff' }}>{Component}</div>
    </ThemeProvider>
  );

  document.getElementsByTagName('body')[0].style = 'margin:0px';

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

export interface InterceptAPIRequestProps<T> {
  alias: string;
  method: Method;
  path: string;
  response?: T | Array<T>;
  statusCode?: number;
}

Cypress.Commands.add(
  'interceptAPIRequest',
  <T extends object>({
    method,
    path,
    response,
    alias,
    statusCode = 200
  }: InterceptAPIRequestProps<T>): void => {
    cy.interceptRequest(
      method,
      path,
      (req, res, ctx) => {
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

Cypress.Commands.add(
  'moveSortableElement',
  ({ element, direction }): void => {
    const key = `{${direction}arrow}`;

    element.type(' ', {
      force: true,
      scrollBehavior: false
    });
    element.eq(-1).type(key, {
      scrollBehavior: false
    });
    element.eq(-1).type(' ', {
      scrollBehavior: false
    });
  }
);

Cypress.Commands.add(
  'moveSortableElementUsingAriaLabel',
  ({ ariaLabel, direction }): void => {
    const key = `{${direction}arrow}`;

    cy.findByLabelText(ariaLabel).type(' ', {
      force: true,
      scrollBehavior: false
    });
    cy.findAllByLabelText(ariaLabel).eq(-1).type(key, {
      scrollBehavior: false
    });
    cy.findAllByLabelText(ariaLabel).eq(-1).type(' ', {
      scrollBehavior: false
    });
  }
);

declare global {
  namespace Cypress {
    interface Chainable {
      interceptAPIRequest: <T extends object>(
        props: InterceptAPIRequestProps<T>
      ) => Cypress.Chainable;
      interceptRequest: (method, path, mock, alias) => Cypress.Chainable;
      mount: ({ Component, options = {} }: MountProps) => Cypress.Chainable;
      moveSortableElement: ({ element, direction }) => void;
      moveSortableElementUsingAriaLabel: ({ ariaLabel, direction }) => void;
      waitFiltersAndListingRequests: () => Cypress.Chainable;
      waitForRequest: (alias) => Cypress.Chainable;
    }
  }
}
