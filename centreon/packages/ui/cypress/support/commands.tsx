import { mount } from 'cypress/react18';
import '@testing-library/cypress/add-commands';
import ThemeProvider from '../../src/ThemeProvider'

Cypress.Commands.add('mount', ({ Component, options }: MountProps) => {
const wrapper = (
    <ThemeProvider>{Component}</ThemeProvider>
  );
  
  return mount(wrapper, options);
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