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
  columnWidth?: number;
  disabled?: boolean;
  enabled?: string;
  segmentedOptions?: Array<{ label: string; value: string }>;
}

const initialize = ({
  enabled = 'use_default',
  disabled = false,
  columnWidth = 800,
  segmentedOptions = options
}: InitializeProps = {}): void => {
  render(
    <div style={{ width: columnWidth }}>
      <Form
        initialValues={{ enabled }}
        inputs={[
          {
            dataTestId: 'enabled',
            fieldName: 'enabled',
            getDisabled: () => disabled,
            group: '',
            label: 'Notification Enabled',
            segmentedButtons: { options: segmentedOptions },
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
    </div>
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

  it('sizes to its labels, not to its column', async () => {
    initialize({ columnWidth: 800 });

    const group = await screen.findByTestId('enabled');
    const widths = (
      await Promise.all(['Yes', 'No', 'Default'].map(button))
    ).map((element) => element.getBoundingClientRect().width);

    // Three equal segments of at least 104px (26 spacing units), left aligned in the column
    // (a pixel apart at most: the group overlaps the borders of its buttons).
    expect(Math.max(...widths) - Math.min(...widths)).toBeLessThanOrEqual(1);
    expect(Math.min(...widths)).toBeGreaterThanOrEqual(104);
    expect(group.getBoundingClientRect().width).toBeLessThan(400);
    expect(group.getBoundingClientRect().left).toBe(
      (await screen.findByText('Notification Enabled')).getBoundingClientRect()
        .left
    );
  });

  it('stays inside a column narrower than its segments', async () => {
    initialize({ columnWidth: 200 });

    const group = await screen.findByTestId('enabled');
    const wrapper = group.parentElement as HTMLElement;

    expect(wrapper.getBoundingClientRect().width).toBeLessThanOrEqual(200);
    expect(wrapper.scrollWidth).toBeGreaterThan(wrapper.clientWidth);
    expect((await button('Default')).scrollWidth).toBeLessThanOrEqual(
      (await button('Default')).clientWidth
    );
  });

  it('widens every segment to the longest label, without wrapping', async () => {
    const longLabel = 'Inherit from the host template';

    initialize({
      segmentedOptions: [
        { label: 'Yes', value: 'true' },
        { label: 'No', value: 'false' },
        { label: longLabel, value: 'use_default' }
      ]
    });

    const yes = await button('Yes');
    const long = await button(longLabel);

    expect(
      Math.abs(
        yes.getBoundingClientRect().width - long.getBoundingClientRect().width
      )
    ).toBeLessThanOrEqual(1);
    expect(long.scrollWidth).toBeLessThanOrEqual(long.clientWidth);
    expect(long.getBoundingClientRect().height).toBe(28);
  });

  it('does not change when disabled', async () => {
    initialize({ disabled: true });

    expect(await button('Yes')).toBeDisabled();
    expect(screen.getByTestId('value')).toHaveTextContent(/^"use_default"$/);
  });
});
