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
    </div>
  );
};

const TestComponent = (): JSX.Element => {
  useFullscreenListener();

  return <ChildComponent />;
};

const initialize = ({ addMin = false }): void => {
  const store = createStore();

  const queryParameters = new Map();

  if (addMin) {
    queryParameters.set('min', '1');
  }

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
  it('enters in fullscreen mode when the button is clicked', () => {
    initialize({});

    cy.get('#test')
      .should('have.attr', 'data-fullscreenActivated', 'false')
      .should('have.attr', 'data-fullscreenEnabled', 'true');
    cy.url().should('not.contain', '&min=1');

    cy.contains(labelEnterFullscreen).realClick();

    cy.get('#test')
      .should('have.attr', 'data-fullscreenActivated', 'true')
      .should('have.attr', 'data-fullscreenEnabled', 'true');
    cy.url().should('contain', '&min=1');

    cy.contains(labelExitFullscreen).realClick();
  });

  it('exits the fullscreen mode when the button is clicked', () => {
    initialize({});

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

  it('toggles the fullscreen mode when the corresponding shortcut is pressed', () => {
    initialize({});

    cy.get('#test')
      .should('have.attr', 'data-fullscreenActivated', 'false')
      .should('have.attr', 'data-fullscreenEnabled', 'true');

    cy.get('#test').realPress(['Alt', 'F']);

    cy.get('#test')
      .should('have.attr', 'data-fullscreenActivated', 'true')
      .should('have.attr', 'data-fullscreenEnabled', 'true');

    cy.get('#test').realPress(['Alt', 'F']);

    cy.get('#test')
      .should('have.attr', 'data-fullscreenActivated', 'false')
      .should('have.attr', 'data-fullscreenEnabled', 'true');
  });

  it('reinitializes the fullscreen state when the min query parameters is set by default', () => {
    initialize({ addMin: true });

    cy.get('#test')
      .should('have.attr', 'data-fullscreenActivated', 'false')
      .should('have.attr', 'data-fullscreenEnabled', 'true');
  });
});
