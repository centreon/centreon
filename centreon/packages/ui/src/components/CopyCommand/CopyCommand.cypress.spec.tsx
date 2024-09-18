import { ThemeMode, userAtom } from '@centreon/ui-context';
import { mount } from 'cypress/react18';
import { Provider, createStore } from 'jotai';
import SnackbarProvider from '../../Snackbar/SnackbarProvider';
import ThemeProvider from '../../ThemeProvider';
import CopyCommand, { CopyCommandProps } from './CopyCommand';

const initialize = (props: CopyCommandProps & { theme?: ThemeMode }): void => {
  const store = createStore();
  store.set(userAtom, { themeMode: props.theme || ThemeMode.light });
  mount(
    <Provider store={store}>
      <ThemeProvider>
        <SnackbarProvider>
          <CopyCommand {...props} />
        </SnackbarProvider>
      </ThemeProvider>
    </Provider>
  );
};

describe('CopyCommand', () => {
  it('displays bash code when props are set', () => {
    initialize({
      text: `# a simple command
echo "hello" | grep "hel"`,
      language: 'bash',
      commandToCopy: 'echo "hello" | grep "hel"'
    });

    cy.contains('# a simple command').should('be.visible');
    cy.contains('echo').should('be.visible');
    cy.contains('bash').should('be.visible');
    cy.findByTestId('Copy command').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays yaml code when props are set', () => {
    initialize({
      text: `key:
  with:
    input: "input"`,
      language: 'yaml',
      commandToCopy: 'echo "hello" | grep "hel"'
    });

    cy.contains('key').should('be.visible');
    cy.contains('"input"').should('be.visible');
    cy.contains('yaml').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays php code when props are set', () => {
    initialize({
      text: `<?php
echo 'Hello ' . htmlspecialchars($_POST["name"]) . '!';
?>`,
      language: 'php',
      commandToCopy: 'echo "hello" | grep "hel"'
    });

    cy.contains('echo').should('be.visible');
    cy.contains('"name"').should('be.visible');
    cy.contains('php').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays json code when props are set', () => {
    initialize({
      text: `{
  "number": 1,
  "boolean": true,
  "array": [
    {
      "string": "text"
    }
  ]
}`,
      language: 'json',
      commandToCopy: 'echo "hello" | grep "hel"'
    });

    cy.contains('"number"').should('be.visible');
    cy.contains('true').should('be.visible');
    cy.contains('json').should('be.visible');

    cy.makeSnapshot();
  });

  it('does not display the copy button when the corresponding prop is not passed', () => {
    initialize({
      text: `{
  "number": 1,
  "boolean": true,
  "array": [
    {
      "string": "text"
    }
  ]
}`,
      language: 'json'
    });

    cy.findByTestId('Copy command').should('not.exist');

    cy.makeSnapshot();
  });

  it('displays the highlighted code in dark mode when the theme is changed', () => {
    initialize({
      text: `{
  "number": 1,
  "boolean": true,
  "array": [
    {
      "string": "text"
    }
  ]
}`,
      language: 'json',
      theme: ThemeMode.dark
    });

    cy.get('.hljs-keyword').should('have.css', 'color', 'rgb(255, 123, 114)');

    cy.makeSnapshot();
  });

  it('copies the command to the clipboard when the button is clicked', () => {
    initialize({
      text: `# a simple command
echo "hello" | grep "hel"`,
      language: 'bash',
      commandToCopy: 'echo "hello" | grep "hel"'
    });

    cy.window()
      .its('navigator.clipboard')
      .then((clipboard) => {
        cy.spy(clipboard, 'writeText').as('writeText');
      });

    cy.findByTestId('Copy command').click();

    cy.get('@writeText').should(
      'have.been.calledOnceWith',
      'echo "hello" | grep "hel"'
    );

    cy.makeSnapshot();
  });
});
