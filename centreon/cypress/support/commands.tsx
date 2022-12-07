/* eslint-disable @typescript-eslint/no-namespace */
import React from 'react';

import { mount } from 'cypress/react18';

import { ThemeProvider } from '@centreon/ui';

window.React = React;

Cypress.Commands.add('mount', ({ Component, options }) => {
  const wrapped = <ThemeProvider>{Component}</ThemeProvider>;

  return mount(wrapped, options);
});

interface MountProps {
  Component: React.Element;
  options?: object;
}

declare global {
  namespace Cypress {
    interface Chainable {
      mount: ({ Component, options = {} }: MountProps) => Cypress.Chainable;
    }
  }
}
