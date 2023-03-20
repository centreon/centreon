/* eslint-disable @typescript-eslint/no-namespace */
import React from 'react';

import { mount } from 'cypress/react18';

import { Box } from '@mui/material';

import { ThemeProvider } from '@centreon/ui';

import 'cypress-msw-interceptor';

interface MountProps {
  Component: React.ReactNode;
  options?: object;
}

export enum Method {
  DELETE = 'DELETE',
  GET = 'GET',
  PATCH = 'PATCH',
  POST = 'POST',
  PUT = 'PUT'
}

Cypress.Commands.add('mount', ({ Component, options }) => {
  const wrapped = (
    <ThemeProvider>
      <Box sx={{ backgroundColor: 'background.paper' }}>{Component}</Box>
    </ThemeProvider>
  );

  document.getElementsByTagName('body')[0].setAttribute('style', 'margin:0px');

  return mount(wrapped, options);
});

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

declare global {
  namespace Cypress {
    interface Chainable {
      interceptAPIRequest: <T extends object>(
        props: InterceptAPIRequestProps<T>
      ) => Cypress.Chainable;
      interceptRequest: (method, path, mock, alias) => Cypress.Chainable;
      mount: ({ Component, options = {} }: MountProps) => Cypress.Chainable;
      waitForRequest: (alias) => Cypress.Chainable;
    }
  }
}
