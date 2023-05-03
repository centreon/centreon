import { mount } from 'cypress/react18';
import '@testing-library/cypress/add-commands';

Cypress.Commands.add('mount', ({ Component, options }: MountProps) => {
    return mount(Component, options);
  });
  
  interface MountProps {
    Component: JSX.Element;
    options?: object;
  }

declare global {
    namespace Cypress {
      interface Chainable {
        mount: ({ Component, options = {} }: MountProps) => Cypress.Chainable;
      }
    }
  }