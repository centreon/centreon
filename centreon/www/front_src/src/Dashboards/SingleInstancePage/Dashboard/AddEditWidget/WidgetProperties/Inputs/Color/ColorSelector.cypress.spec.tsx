import { createStore, Provider } from 'jotai';
import { Formik } from 'formik';

import { hasEditPermissionAtom, isEditingAtom } from '../../../../atoms';
import { labelBackgroundColor } from '../../../../translatedLabels';

import colors from './colors.json';
import ColorSelector from './Color';

const initialize = ({
  isInGroup = false,
  canEdit = true,
  hasValue = false
}): void => {
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
              test: hasValue ? colors[1] : null
            }
          }}
          onSubmit={cy.stub()}
        >
          <ColorSelector
            isInGroup={isInGroup}
            label={labelBackgroundColor}
            propertyName="test"
          />
        </Formik>
      </Provider>
    )
  });
};

describe('Color selector', () => {
  it('displays default color when any color was previously selected', () => {
    initialize({});

    cy.findByTestId('color-chip-#255891').should('be.visible');

    cy.makeSnapshot();
  });

  it('selects another color when a color is selected', () => {
    initialize({ hasValue: true });

    cy.findByTestId('color selector').click();
    cy.findByTestId('color-chip-#076059').click();

    cy.findByTestId('color-chip-#076059').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays the color selector in a group when a props is set', () => {
    initialize({ isInGroup: true });

    cy.contains(labelBackgroundColor).should('be.visible');

    cy.makeSnapshot();
  });

  it('displays the color selector as disabled when user does not have the right to edit the field', () => {
    initialize({ canEdit: false });

    cy.findByTestId('color selector').should('be.disabled');

    cy.makeSnapshot();
  });
});
