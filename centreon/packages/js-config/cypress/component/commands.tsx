/* eslint-disable @typescript-eslint/no-namespace */
import React from 'react';

import { mount } from 'cypress/react18';
import { equals, isNil } from 'ramda';

import { Box, CssBaseline } from '@mui/material';

import { ThemeProvider } from '@centreon/ui';

import '@testing-library/cypress/add-commands';
import 'cypress-msw-interceptor';
import 'cypress-real-events';

import disableMotion from './disableCssTransitions';

interface MountProps {
  Component: React.ReactNode;
  options?: object;
}
interface Resolution {
  height: number;
  width: number;
}

interface MakeSnapshotWithCustomResolution {
  resolution: Resolution;
  title: string;
}

export enum Method {
  DELETE = 'DELETE',
  GET = 'GET',
  PATCH = 'PATCH',
  POST = 'POST',
  PUT = 'PUT'
}

Cypress.Commands.add('mount', ({ Component, options = {} }) => {
  const wrapped = (
    <ThemeProvider>
      <Box
        sx={{
          backgroundColor: 'background.paper',
          height: '100%',
          width: '100%'
        }}
      >
        {Component}
      </Box>
      <CssBaseline />
    </ThemeProvider>
  );

  return mount(wrapped, options);
});

interface Query {
  name: string;
  value: string;
}

export interface InterceptAPIRequestProps<T> {
  alias: string;
  delay?: number;
  method: Method;
  path: string;
  query?: Query;
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
    query,
    statusCode = 200,
    delay = 500
  }: InterceptAPIRequestProps<T>): void => {
    cy.interceptRequest(
      method,
      path.replace('./', '**'),
      (req, res, ctx) => {
        const getQuery = req?.url?.searchParams?.get(query?.name);
        if (query && equals(query.value, getQuery)) {
          return res(
            ctx.delay(delay),
            ctx.json(response),
            ctx.status(statusCode)
          );
        }
        if (!getQuery && isNil(query)) {
          return res(
            ctx.delay(delay),
            ctx.json(response),
            ctx.status(statusCode)
          );
        }

        return null;
      },
      alias
    );
  }
);

interface MoveSortableElementProps {
  direction: 'up' | 'down' | 'left' | 'right';
  element: Cypress.Chainable<JQuery<HTMLElement>>;
  times?: number;
}

Cypress.Commands.add(
  'moveSortableElement',
  ({ element, direction, times = 1 }: MoveSortableElementProps): void => {
    const key = `{${direction}arrow}`;

    element.type(' ', {
      force: true,
      scrollBehavior: false
    });

    Array.from({ length: times }).forEach(() => {
      element.eq(-1).type(key, {
        scrollBehavior: false
      });
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

Cypress.Commands.add('adjustViewport', () => cy.viewport(1280, 581));

Cypress.Commands.add('makeSnapshot', (title?: string) => {
  cy.adjustViewport();
  cy.matchImageSnapshot(title);
});

Cypress.Commands.add(
  'makeSnapshotWithCustomResolution',
  ({ title, resolution }: MakeSnapshotWithCustomResolution) => {
    const { width, height } = resolution;
    cy.viewport(width, height);
    cy.matchImageSnapshot(title);
  }
);

Cypress.Commands.add('cssDisableMotion', (): void => {
  Cypress.on('window:before:load', (cyWindow) => {
    disableMotion(cyWindow);
  });
});

declare global {
  namespace Cypress {
    interface Chainable {
      adjustViewport: () => Cypress.Chainable;
      cssDisableMotion: () => Cypress.Chainable;
      getRequestCalls: (alias) => Cypress.Chainable;
      interceptAPIRequest: <T extends object>(
        props: InterceptAPIRequestProps<T>
      ) => Cypress.Chainable;
      interceptRequest: (method, path, mock, alias) => Cypress.Chainable;
      makeSnapshot: (title?: string) => void;
      makeSnapshotWithCustomResolution: ({
        title,
        resolution
      }: MakeSnapshotWithCustomResolution) => Cypress.Chainable;
      mount: ({ Component, options }: MountProps) => Cypress.Chainable;
      moveSortableElement: ({
        element,
        direction,
        times
      }: MoveSortableElementProps) => void;
      moveSortableElementUsingAriaLabel: ({ ariaLabel, direction }) => void;
      waitForRequest: (alias) => Cypress.Chainable;
    }
  }
}
