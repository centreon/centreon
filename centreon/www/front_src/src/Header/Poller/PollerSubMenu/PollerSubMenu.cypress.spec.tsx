import { SnackbarProvider, TestQueryProvider } from '@centreon/ui';
import {
  platformFeaturesAtom,
  ThemeMode,
  userAtom
} from '@centreon/ui-context';

import { createStore, getDefaultStore, Provider } from 'jotai';
import { BrowserRouter as Router } from 'react-router';

import {
  labelAllPollers,
  labelBeta,
  labelConfigurePollers,
  labelCreateNewPoller
} from '../translatedLabels';
import { PollerSubMenu } from './PollerSubMenu';

interface InitializeOptions {
  isCloudPlatform?: boolean;
  themeMode?: ThemeMode;
}

const initialize = ({
  isCloudPlatform = false,
  themeMode = ThemeMode.light
}: InitializeOptions = {}): void => {
  const store = createStore();

  store.set(platformFeaturesAtom, { featureFlags: {}, isCloudPlatform });

  // cy.mount wraps the component in a ThemeProvider that sits *outside* this
  // Provider, so it reads userAtom from the default store rather than ours.
  getDefaultStore().set(userAtom, {
    alias: 'admin',
    defaultPage: '/monitoring/resources',
    isExportButtonEnabled: true,
    locale: 'en',
    name: 'admin',
    themeMode,
    timezone: 'Europe/Paris',
    useDeprecatedPages: false
  });

  cy.mount({
    Component: (
      <TestQueryProvider>
        <Provider store={store}>
          <Router>
            <SnackbarProvider maxSnackbars={2}>
              {/* The submenu renders inside a popover that shrink-wraps its
                  content, so constrain the width here — mounted unbounded, the
                  layout would not reflect what users actually see. */}
              <div className="w-fit">
                <PollerSubMenu
                  allPollerLabel={labelAllPollers}
                  closeSubMenu={cy.stub()}
                  displayPollerButton
                  exportConfig={{ isExportButtonEnabled: true }}
                  issues={[]}
                  pollerConfig={{
                    label: labelConfigurePollers,
                    redirect: cy.stub(),
                    testId: labelConfigurePollers
                  }}
                  pollerCount={3}
                />
              </div>
            </SnackbarProvider>
          </Router>
        </Provider>
      </TestQueryProvider>
    )
  });
};

describe('PollerSubMenu', () => {
  describe('Create new poller entry', () => {
    it('displays the button on an on-premise platform', () => {
      initialize();

      cy.findByTestId(labelCreateNewPoller).should('be.visible');
    });

    it('hides the button on a cloud platform', () => {
      initialize({ isCloudPlatform: true });

      cy.findByTestId(labelCreateNewPoller).should('not.exist');
    });

    it('displays the beta chip next to the button', () => {
      initialize();

      cy.findByText(labelBeta.toLocaleUpperCase()).should('be.visible');
    });

    it('does not open the modal when the beta chip is clicked', () => {
      initialize();

      cy.findByText(labelBeta.toLocaleUpperCase()).click();

      cy.findByRole('dialog').should('not.exist');
    });
  });

  [ThemeMode.light, ThemeMode.dark].forEach((themeMode) => {
    it(`matches the current snapshot in ${themeMode} theme`, () => {
      initialize({ themeMode });

      cy.findByTestId(labelCreateNewPoller).should('be.visible');
      cy.findByText(labelBeta.toLocaleUpperCase()).should('be.visible');

      cy.makeSnapshot();
    });
  });
});
