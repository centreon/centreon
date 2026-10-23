import { Typography } from '@mui/material';

import { rs } from '@rstest/core';
import { render } from '@test-setup/render';
import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
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

interface InitializeProps {
  disabled?: boolean;
  enabled?: string;
}

const initialize = ({
  enabled = 'use_default',
  disabled = false
}: InitializeProps = {}): void => {
  render(
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
      submit={rs.fn()}
      validationSchema={object()}
    />
  );
};

const button = (label: string): Promise<HTMLElement> =>
  screen.findByRole('button', { name: label });

describe('Segmented buttons', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  it('displays every option and marks the current one', async () => {
    initialize();

    expect(await screen.findByText('Notification Enabled')).toBeVisible();
    expect(await button('Yes')).toHaveAttribute('aria-pressed', 'false');
    expect(await button('No')).toHaveAttribute('aria-pressed', 'false');
    expect(await button('Default')).toHaveAttribute('aria-pressed', 'true');
  });

  it('stores the value of the clicked option as given', async () => {
    initialize();

    await userEvent.click(await button('Yes'));

    // A string, not the boolean the radio would store.
    await waitFor(() => {
      expect(screen.getByTestId('value')).toHaveTextContent(/^"true"$/);
    });
    expect(await button('Yes')).toHaveAttribute('aria-pressed', 'true');
    expect(await button('Default')).toHaveAttribute('aria-pressed', 'false');

    await userEvent.click(await button('No'));

    await waitFor(() => {
      expect(screen.getByTestId('value')).toHaveTextContent(/^"false"$/);
    });
  });

  it('starts on the initial value', async () => {
    initialize({ enabled: 'false' });

    expect(await button('No')).toHaveAttribute('aria-pressed', 'true');
  });

  it('does not change when disabled', async () => {
    initialize({ disabled: true });

    expect(await button('Yes')).toBeDisabled();
    expect(screen.getByTestId('value')).toHaveTextContent(/^"use_default"$/);
  });
});
