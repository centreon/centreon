// @ts-expect-error ts-migrate(2307)
import widgetDataConfiguration from 'centreon-widgets/centreon-widget-data/moduleFederation.json';
/* eslint-disable import/no-unresolved */
// @ts-expect-error ts-migrate(2307)
import widgetTextConfiguration from 'centreon-widgets/centreon-widget-text/moduleFederation.json';
import { Provider, createStore } from 'jotai';

import { Button } from '@mui/material';

import { federatedWidgetsAtom } from '@centreon/ui-context';

import { Remote } from '../../federatedModules/Load';
import FederatedComponentFallback from '../../federatedModules/Load/FederatedComponentFallback';
import FederatedPageFallback from '../../federatedModules/Load/FederatedPageFallback';
import { labelCannotLoadModule } from '../../federatedModules/translatedLabels';

import LoadableComponentsContainer from '.';

const initialize = ({
  path,
  styleMenuSkeleton,
  children
}: {
  children?;
  path;
  styleMenuSkeleton?;
}): void => {
  const federatedWidgets = [
    {
      ...widgetTextConfiguration,
      moduleFederationName: 'centreon-widget-text/src'
    },
    {
      ...widgetDataConfiguration,
      moduleFederationName: 'centreon-widget-data/src'
    }
  ];

  const store = createStore();
  store.set(federatedWidgetsAtom, federatedWidgets);

  cy.mount({
    Component: (
      <Provider store={store}>
        <LoadableComponentsContainer
          path={path}
          styleMenuSkeleton={styleMenuSkeleton}
        >
          {children}
        </LoadableComponentsContainer>
      </Provider>
    )
  });
};

describe('Federated component', () => {
  it('loads a federated component', () => {
    initialize({
      path: widgetTextConfiguration.federatedComponentsConfiguration[0].path
    });

    cy.contains('Hello world').should('be.visible');

    cy.makeSnapshot();
  });

  it('loads a federated component with a custom loading', () => {
    initialize({
      path: widgetTextConfiguration.federatedComponentsConfiguration[0].path,
      styleMenuSkeleton: {
        backgroundColor: 'red',
        border: '1px solid green'
      }
    });

    cy.contains('Hello world').should('be.visible');

    cy.makeSnapshot();
  });

  it('loads a federated component with a children', () => {
    initialize({
      children: <Button>Button</Button>,
      path: widgetDataConfiguration.federatedComponentsConfiguration[0].path
    });

    cy.get('button').contains('Button').should('be.visible');

    cy.makeSnapshot();
  });

  it('loads a federated component with a children and a custom loading', () => {
    initialize({
      children: <Button>Button</Button>,
      path: widgetDataConfiguration.federatedComponentsConfiguration[0].path,
      styleMenuSkeleton: {
        backgroundColor: 'red',
        border: '1px solid green'
      }
    });

    cy.get('button').contains('Button').should('be.visible');

    cy.makeSnapshot();
  });
});

const initializePage = ({
  component,
  moduleFederationName,
  moduleName,
  remoteEntry,
  styleMenuSkeleton,
  children
}: {
  children?;
  component;
  moduleFederationName;
  moduleName;
  remoteEntry;
  styleMenuSkeleton?;
}): void => {
  const federatedWidgets = [
    {
      ...widgetTextConfiguration,
      moduleFederationName: 'centreon-widget-text/src'
    },
    {
      ...widgetDataConfiguration,
      moduleFederationName: 'centreon-widget-data/src'
    }
  ];

  const store = createStore();
  store.set(federatedWidgetsAtom, federatedWidgets);

  cy.mount({
    Component: (
      <Provider store={store}>
        <Remote
          component={component}
          moduleFederationName={moduleFederationName}
          moduleName={moduleName}
          remoteEntry={remoteEntry}
          styleMenuSkeleton={styleMenuSkeleton}
        >
          {children}
        </Remote>
      </Provider>
    )
  });
};

describe('Federated page', () => {
  it('loads a page as remote component', () => {
    initializePage({
      component:
        widgetTextConfiguration.federatedComponentsConfiguration[0].path,
      moduleFederationName: 'centreon-widget-text/src',
      moduleName: widgetTextConfiguration.moduleName,
      remoteEntry: widgetTextConfiguration.remoteEntry
    });

    cy.contains('Hello world').should('be.visible');

    cy.makeSnapshot();
  });
  it('loads a page as remote component with a custom loading', () => {
    initializePage({
      component:
        widgetTextConfiguration.federatedComponentsConfiguration[0].path,
      moduleFederationName: 'centreon-widget-text/src',
      moduleName: widgetTextConfiguration.moduleName,
      remoteEntry: widgetTextConfiguration.remoteEntry,
      styleMenuSkeleton: {
        backgroundColor: 'red'
      }
    });

    cy.contains('Hello world').should('be.visible');

    cy.makeSnapshot();
  });

  it('loads a federated component with a children', () => {
    initializePage({
      children: <Button>Button</Button>,
      component:
        widgetDataConfiguration.federatedComponentsConfiguration[0].path,
      moduleFederationName: 'centreon-widget-data/src',
      moduleName: widgetDataConfiguration.moduleName,
      remoteEntry: widgetDataConfiguration.remoteEntry
    });

    cy.get('button').contains('Button').should('be.visible');

    cy.makeSnapshot();
  });

  it('loads a federated component with a children and a custom loading', () => {
    initializePage({
      children: <Button>Button</Button>,
      component:
        widgetDataConfiguration.federatedComponentsConfiguration[0].path,
      moduleFederationName: 'centreon-widget-data/src',
      moduleName: widgetDataConfiguration.moduleName,
      remoteEntry: widgetDataConfiguration.remoteEntry,
      styleMenuSkeleton: {
        backgroundColor: 'red',
        border: '1px solid green'
      }
    });

    cy.get('button').contains('Button').should('be.visible');

    cy.makeSnapshot();
  });
});

const initializeFallback = (Fallback): void => {
  cy.mount({
    Component: <Fallback />
  });
};

describe('Fallback', () => {
  it('displays the fallback page', () => {
    initializeFallback(FederatedPageFallback);

    cy.contains(labelCannotLoadModule).should('be.visible');
  });

  it('displays the fallback component', () => {
    initializeFallback(FederatedComponentFallback);

    cy.contains(labelCannotLoadModule).should('be.visible');

    cy.makeSnapshot();
  });
});
