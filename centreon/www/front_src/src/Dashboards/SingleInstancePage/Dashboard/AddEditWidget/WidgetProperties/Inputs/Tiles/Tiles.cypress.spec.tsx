import { Formik } from 'formik';

import { editProperties } from '../../../../hooks/useCanEditDashboard';

import WidgetTiles from './Tiles';
import { labelDisplayUpTo, labelTiles } from './translatedLabels';

const initialize = (canEdit = true): void => {
  cy.stub(editProperties, 'useCanEditProperties').returns({
    canEdit,
    canEditField: canEdit
  });

  cy.mount({
    Component: (
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
    )
  });
};

describe('Tiles', () => {
  beforeEach(initialize);

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
