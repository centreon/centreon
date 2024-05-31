import { Formik } from 'formik';
import { createStore, Provider } from 'jotai';

import { hasEditPermissionAtom, isEditingAtom } from '../../../../atoms';

import ButtonGroup from './ButtonGroup';

import { FederatedWidgetOptionType } from 'www/front_src/src/federatedModules/models';

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

const initialize = ({ isInGroup = false, canEdit = true }): void => {
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
            subInputs={[
              {
                displayValue: 'button3',
                input: {
                  defaultValue: 'sample',
                  label: 'Sub input 1',
                  type: FederatedWidgetOptionType.textfield
                },
                name: 'sub1'
              },
              {
                displayValue: 'button3',
                input: {
                  defaultValue: 'text',
                  label: 'Sub input 2',
                  type: FederatedWidgetOptionType.textfield
                },
                name: 'sub2'
              },
              {
                displayValue: 'button4',
                input: {
                  defaultValue: 'radio1',
                  label: 'Sub input 3',
                  options: [
                    {
                      id: 'radio1',
                      name: 'Radio 1'
                    },
                    {
                      id: 'radio2',
                      name: 'Radio 2'
                    }
                  ],
                  type: FederatedWidgetOptionType.radio
                },
                name: 'sub3'
              }
            ]}
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
