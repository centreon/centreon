import { Typography } from '@mui/material';

import { useFormikContext } from 'formik';
import { object } from 'yup';

import { Form } from '../Form';
import { InputType } from './models';

const options = [
  { label: 'Yes', value: 'true' },
  { label: 'No', value: 'false' },
  { label: 'Default', value: 'use_default' }
];

const ValueDisplay = (): JSX.Element => {
  const { values } = useFormikContext<{ enabled: string }>();

  return (
    <Typography data-testid="value">
      {JSON.stringify(values.enabled)}
    </Typography>
  );
};

const initialize = ({
  enabled = 'use_default',
  disabled = false
}: {
  disabled?: boolean;
  enabled?: string;
} = {}): void => {
  cy.mount({
    Component: (
      <Form
        initialValues={{ enabled }}
        inputs={[
          {
            dataTestId: 'enabled',
            fieldName: 'enabled',
            getDisabled: () => disabled,
            group: '',
            label: 'Notification Enabled',
            segmentedButtons: { options },
            type: InputType.SegmentedButtons
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

const button = (label: string): Cypress.Chainable =>
  cy.findByRole('button', { name: label });

describe('Segmented buttons', () => {
  it('displays every option and marks the current one', () => {
    initialize();

    cy.contains('Notification Enabled').should('be.visible');
    button('Yes').should('have.attr', 'aria-pressed', 'false');
    button('No').should('have.attr', 'aria-pressed', 'false');
    button('Default').should('have.attr', 'aria-pressed', 'true');
  });

  it('stores the value of the clicked option as given', () => {
    initialize();

    button('Yes').click();

    // A string, not the boolean the radio would store.
    cy.findByTestId('value').should('have.text', '"true"');
    button('Yes').should('have.attr', 'aria-pressed', 'true');
    button('Default').should('have.attr', 'aria-pressed', 'false');

    button('No').click();

    cy.findByTestId('value').should('have.text', '"false"');
  });

  it('starts on the initial value', () => {
    initialize({ enabled: 'false' });

    button('No').should('have.attr', 'aria-pressed', 'true');
  });

  it('does not change when disabled', () => {
    initialize({ disabled: true });

    button('Yes').should('be.disabled');
    cy.findByTestId('value').should('have.text', '"use_default"');
  });
});
