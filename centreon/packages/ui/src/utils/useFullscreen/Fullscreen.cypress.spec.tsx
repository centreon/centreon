import { Provider, createStore } from 'jotai';
import { BrowserRouter } from 'react-router-dom';

import { Button } from '@mui/material';

import { useFullscreen } from './useFullscreen';
import { router, useFullscreenListener } from './useFullscreenListener';

const labelEnterFullscreen = 'Enter fullscreen';
const labelExitFullscreen = 'Exit fullscreen';

const ChildComponent = (): JSX.Element => {
  const { toggleFullscreen, fullscreenEnabled, isFullscreenActivated } =
    useFullscreen();

  return (
    <div
      data-fullscreenActivated={isFullscreenActivated}
      data-fullscreenEnabled={fullscreenEnabled}
      id="test"
    >
      <Button onClick={() => toggleFullscreen(document.body)}>
        {isFullscreenActivated ? labelExitFullscreen : labelEnterFullscreen}
      </Button>
      <input id="input" />
      <textarea id="textarea" />
    </div>
  );
};

const TestComponent = (): JSX.Element => {
  useFullscreenListener();

  return <ChildComponent />;
};

const initialize = (): void => {
  const store = createStore();

  const queryParameters = new Map();

  cy.stub(router, 'useSearchParams', () => queryParameters);

  cy.mount({
    Component: (
      <BrowserRouter>
        <Provider store={store}>
          <TestComponent />
        </Provider>
      </BrowserRouter>
    )
  });
};

describe('Fullscreen', () => {
  it('enters fullscreen mode when the button is clicked', () => {
    initialize();

    cy.get('#test')
      .should('have.attr', 'data-fullscreenActivated', 'false')
      .should('have.attr', 'data-fullscreenEnabled', 'true');

    cy.contains(labelEnterFullscreen).realClick();

    cy.get('#test')
      .should('have.attr', 'data-fullscreenActivated', 'true')
      .should('have.attr', 'data-fullscreenEnabled', 'true');

    cy.contains(labelExitFullscreen).realClick();
  });

  it('exits fullscreen mode when the button is clicked', () => {
    initialize();

    cy.get('#test')
      .should('have.attr', 'data-fullscreenActivated', 'false')
      .should('have.attr', 'data-fullscreenEnabled', 'true');

    cy.contains(labelEnterFullscreen).realClick();

    cy.get('#test')
      .should('have.attr', 'data-fullscreenActivated', 'true')
      .should('have.attr', 'data-fullscreenEnabled', 'true');

    cy.contains(labelExitFullscreen).realClick();

    cy.get('#test')
      .should('have.attr', 'data-fullscreenActivated', 'false')
      .should('have.attr', 'data-fullscreenEnabled', 'true');
  });

  it('toggles fullscreen mode when the corresponding shortcut is clicked', () => {
    initialize();

    cy.get('#test')
      .should('have.attr', 'data-fullscreenActivated', 'false')
      .should('have.attr', 'data-fullscreenEnabled', 'true');

    cy.get('#test').realPress(['F']);

    cy.get('#test')
      .should('have.attr', 'data-fullscreenActivated', 'true')
      .should('have.attr', 'data-fullscreenEnabled', 'true');

    cy.get('#test').realPress(['F']);

    cy.get('#test')
      .should('have.attr', 'data-fullscreenActivated', 'false')
      .should('have.attr', 'data-fullscreenEnabled', 'true');
  });

  ['input', 'textarea'].forEach((tag) => {
    it(`cannot toggle fullscreen feature using the shortcut when ${
      tag === 'input' ? 'an' : 'a'
    } ${tag} is focused`, () => {
      initialize();

      cy.get('#test')
        .should('have.attr', 'data-fullscreenActivated', 'false')
        .should('have.attr', 'data-fullscreenEnabled', 'true');

      cy.get(`#${tag}`).focus();

      cy.get('#test').realPress(['F']);

      cy.get('#test')
        .should('have.attr', 'data-fullscreenActivated', 'false')
        .should('have.attr', 'data-fullscreenEnabled', 'true');
      cy.get(`#${tag}`).should('have.value', 'F');
    });
  });
});
