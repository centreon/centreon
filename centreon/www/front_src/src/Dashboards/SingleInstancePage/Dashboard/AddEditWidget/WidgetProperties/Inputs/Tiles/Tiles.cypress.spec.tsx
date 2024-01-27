import { Formik } from 'formik';
import { Provider, createStore } from 'jotai';

import { hasEditPermissionAtom, isEditingAtom } from '../../../../atoms';

import WidgetTiles from './Tiles';
import { labelDisplayUpTo, labelTiles } from './translatedLabels';

const initialize = (canEdit = true): void => {
  const store = createStore();

  store.set(hasEditPermissionAtom, canEdit);
  store.set(isEditingAtom, canEdit);

  cy.mount({
    Component: (
      <Provider store={store}>
        <Formik
          initialValues={{
            moduleName: 'widget',
            options: {
              tiles: 100
            }
          }}
          onSubmit={cy.stub()}
        >
          <WidgetTiles label="" propertyName="tiles" />
        </Formik>
      </Provider>
    )
  });
};

describe('Tiles', () => {
  beforeEach(() => initialize());

  it('displays the tile input with an initial value', () => {
    cy.contains(labelDisplayUpTo).should('be.visible');
    cy.contains(labelTiles).should('be.visible');
    cy.findByLabelText(labelTiles).should('have.value', 100);

    cy.makeSnapshot();
  });

  it('changes the tiles value when the input is updated', () => {
    cy.findByLabelText(labelTiles).type('{selectall}50');
    cy.findByLabelText(labelTiles).should('have.value', 50);

    cy.makeSnapshot();
  });
});

describe('Tiles disabled', () => {
  it('displays the tile input with an initial value', () => {
    initialize(false);

    cy.findByLabelText(labelTiles).should('be.disabled');

    cy.makeSnapshot();
  });
});
