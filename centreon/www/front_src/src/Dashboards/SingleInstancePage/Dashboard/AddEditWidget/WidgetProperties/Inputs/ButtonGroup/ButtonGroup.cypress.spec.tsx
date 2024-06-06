import { Formik } from 'formik';
import { createStore, Provider } from 'jotai';

import { hasEditPermissionAtom, isEditingAtom } from '../../../../atoms';

import ButtonGroup from './ButtonGroup';

const buttons = [
  {
    id: 'button1',
    name: 'Button 1'
  },
  {
    id: 'button2',
    name: 'Button 2'
  },
  {
    id: 'button3',
    name: 'Button 3'
  },
  {
    id: 'button4',
    name: 'Button 4'
  }
];

const initialize = ({
  isInGroup = false,
  canEdit = true,
  secondaryLabel = undefined
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
              test: 'button1'
            }
          }}
          onSubmit={cy.stub()}
        >
          <ButtonGroup
            isInGroup={isInGroup}
            label="Buttons"
            options={buttons}
            propertyName="test"
            secondaryLabel={secondaryLabel}
            type=""
          />
        </Formik>
      </Provider>
    )
  });
};

describe('Button group', () => {
  it('displays button group with a pre selected button', () => {
    initialize({});

    cy.findByLabelText('Button 1')
      .should('have.attr', 'data-selected')
      .and('equal', 'true');
    cy.findByLabelText('Button 2')
      .should('have.attr', 'data-selected')
      .and('equal', 'false');
    cy.findByLabelText('Button 3')
      .should('have.attr', 'data-selected')
      .and('equal', 'false');

    cy.makeSnapshot();
  });

  it('displays the title as not in a group when the prop is set', () => {
    initialize({ isInGroup: true });

    cy.contains('Buttons').should('be.visible');

    cy.makeSnapshot();
  });

  it('changes the selected button when a button is clicked', () => {
    initialize({});

    cy.findByLabelText('Button 1')
      .should('have.attr', 'data-selected')
      .and('equal', 'true');
    cy.findByLabelText('Button 2')
      .should('have.attr', 'data-selected')
      .and('equal', 'false');

    cy.findByLabelText('Button 2').click();

    cy.findByLabelText('Button 1')
      .should('have.attr', 'data-selected')
      .and('equal', 'false');
    cy.findByLabelText('Button 2')
      .should('have.attr', 'data-selected')
      .and('equal', 'true');

    cy.makeSnapshot();
  });

  it('displays the secondary label when the corresponding prop is set', () => {
    initialize({ secondaryLabel: 'This is a secondary label' });

    cy.findByTestId('secondary-label-test').realHover();

    cy.contains('This is a secondary label').should('be.visible');

    cy.makeSnapshot();
  });
});

describe('Button group disabled', () => {
  it('displays button group as disabled', () => {
    initialize({ canEdit: false });

    cy.findByLabelText('Button 1').should('be.disabled');
    cy.findByLabelText('Button 2').should('be.disabled');
    cy.findByLabelText('Button 3').should('be.disabled');

    cy.makeSnapshot();
  });
});
