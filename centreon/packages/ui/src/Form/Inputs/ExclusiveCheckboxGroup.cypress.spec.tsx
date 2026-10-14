import { Typography } from '@mui/material';

import { useFormikContext } from 'formik';
import { object } from 'yup';

import { Form } from '../Form';
import { InputType } from './models';

const options = ['Down', 'Unreachable', 'Recovery', 'None'];

const ValueDisplay = (): JSX.Element => {
  const { values } = useFormikContext<{
    notificationOptions: Array<string> | null;
  }>();

  return (
    <Typography data-testid="value">
      {JSON.stringify(values.notificationOptions)}
    </Typography>
  );
};

const initialize = ({
  notificationOptions = [],
  disabled = false
}: {
  disabled?: boolean;
  notificationOptions?: Array<string> | null;
} = {}): void => {
  cy.mount({
    Component: (
      <Form
        initialValues={{ notificationOptions }}
        inputs={[
          {
            dataTestId: 'notification-options',
            exclusiveCheckboxGroup: {
              direction: 'horizontal',
              exclusiveLabel: 'No notifications',
              exclusiveOption: 'None',
              options
            },
            fieldName: 'notificationOptions',
            getDisabled: () => disabled,
            group: '',
            label: 'Notification options',
            type: InputType.ExclusiveCheckboxGroup
          },
          {
            custom: { Component: ValueDisplay },
            fieldName: 'value',
            group: '',
            label: 'value',
            type: InputType.Custom
          }
        ]}
        submit={cy.stub()}
        validationSchema={object()}
      />
    )
  });
};

const toggle = (): Cypress.Chainable => cy.findByLabelText('No notifications');

const expectValue = (value: Array<string> | null): void => {
  cy.findByTestId('value').should('have.text', JSON.stringify(value));
};

describe('Exclusive checkbox group', () => {
  it('displays the toggle and every option except the exclusive one', () => {
    initialize();

    toggle().should('not.be.checked');
    cy.findByLabelText('Down').should('exist');
    cy.findByLabelText('Unreachable').should('exist');
    cy.findByLabelText('Recovery').should('exist');
    cy.findByLabelText('None').should('not.exist');

    cy.makeSnapshot();
  });

  it('keeps the inherited state when nothing is selected', () => {
    initialize();

    toggle().should('not.be.checked');
    cy.findByLabelText('Down').should('not.be.checked').and('be.enabled');
    expectValue([]);
  });

  it('treats a null value as the inherited state', () => {
    initialize({ notificationOptions: null });

    toggle().should('not.be.checked');
    cy.findByLabelText('Down').should('not.be.checked').and('be.enabled');
  });

  it('displays the exclusive state from the initial value', () => {
    initialize({ notificationOptions: ['None'] });

    toggle().should('be.checked');
    cy.findByLabelText('Down').should('not.be.checked').and('be.disabled');
    expectValue(['None']);

    cy.makeSnapshot();
  });

  it('displays a selection from the initial value', () => {
    initialize({ notificationOptions: ['Down', 'Recovery'] });

    toggle().should('not.be.checked');
    cy.findByLabelText('Down').should('be.checked');
    cy.findByLabelText('Unreachable').should('not.be.checked');
    cy.findByLabelText('Recovery').should('be.checked');
    expectValue(['Down', 'Recovery']);
  });

  it('goes from the inherited state to a selection and back', () => {
    initialize();

    cy.findByLabelText('Down').click();
    cy.findByLabelText('Recovery').click();
    expectValue(['Down', 'Recovery']);

    cy.findByLabelText('Down').click();
    cy.findByLabelText('Recovery').click();
    expectValue([]);
    toggle().should('not.be.checked');
  });

  it('goes from the inherited state to the exclusive state and back', () => {
    initialize();

    toggle().click();
    toggle().should('be.checked');
    expectValue(['None']);

    toggle().click();
    toggle().should('not.be.checked');
    expectValue([]);
  });

  it('clears the selection when the toggle is turned on and leaves it empty when turned off', () => {
    initialize({ notificationOptions: ['Down', 'Recovery'] });

    toggle().click();
    expectValue(['None']);
    cy.findByLabelText('Down').should('not.be.checked').and('be.disabled');
    cy.findByLabelText('Recovery').should('not.be.checked').and('be.disabled');

    toggle().click();
    expectValue([]);
    cy.findByLabelText('Down').should('not.be.checked').and('be.enabled');

    cy.findByLabelText('Unreachable').click();
    expectValue(['Unreachable']);
  });

  it('prevents ticking a box while the toggle is on', () => {
    initialize({ notificationOptions: ['None'] });

    cy.findByLabelText('Down').click({ force: true });
    cy.findByLabelText('Down').should('not.be.checked');
    expectValue(['None']);
  });

  it('disables both the toggle and the checkboxes when the input is disabled', () => {
    initialize({ disabled: true, notificationOptions: ['Down'] });

    toggle().should('be.disabled');
    cy.findByLabelText('Down').should('be.checked').and('be.disabled');
    expectValue(['Down']);
  });
});
