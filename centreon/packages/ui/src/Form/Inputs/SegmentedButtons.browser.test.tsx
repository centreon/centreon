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

    const label = await screen.findByText('Notification Enabled');

    expect(label).toBeVisible();
    // Bold, as the design shows the label above the buttons.
    expect(getComputedStyle(label).fontWeight).toBe('700');
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

  it('sizes each segment to its label, at least 104px, whatever the column', async () => {
    initialize({
      columnWidth: 800,
      segmentedOptions: [
        { label: 'Yes', value: 'true' },
        { label: 'No', value: 'false' },
        { label: 'Inherit from template', value: 'use_default' }
      ]
    });

    const group = await screen.findByTestId('enabled');
    const [yes, no, inherit] = (
      await Promise.all(['Yes', 'No', 'Inherit from template'].map(button))
    ).map((element) => element.getBoundingClientRect().width);

    // As in the design: Yes and No keep 104px, the longer label grows alone
    // (the group overlaps the borders of its buttons, a pixel at most).
    expect(yes).toBeGreaterThanOrEqual(104);
    expect(Math.abs(yes - no)).toBeLessThanOrEqual(1);
    expect(yes).toBeLessThan(110);
    expect(inherit).toBeGreaterThan(yes + 20);
    expect(group.getBoundingClientRect().width).toBeLessThan(500);
    expect(group.getBoundingClientRect().left).toBe(
      (await screen.findByText('Notification Enabled')).getBoundingClientRect()
        .left
    );
  });

  it('keeps three segments of 104px when the labels are short', async () => {
    initialize({ columnWidth: 800 });

    const widths = (
      await Promise.all(['Yes', 'No', 'Default'].map(button))
    ).map((element) => element.getBoundingClientRect().width);

    expect(Math.min(...widths)).toBeGreaterThanOrEqual(104);
    expect(Math.max(...widths) - Math.min(...widths)).toBeLessThanOrEqual(1);
  });

  it('cuts a label wider than the room left with an ellipsis', async () => {
    const longLabel =
      'Vorlage erben und alle Werte der Vorlage ohne Ausnahme übernehmen';

    initialize({
      columnWidth: 420,
      segmentedOptions: [
        { label: 'Yes', value: 'true' },
        { label: 'No', value: 'false' },
        { label: longLabel, value: 'use_default' }
      ]
    });

    const long = await button(longLabel);
    const text = long.firstElementChild as HTMLElement;

    expect(text.scrollWidth).toBeGreaterThan(text.clientWidth);
    expect(getComputedStyle(text).textOverflow).toBe('ellipsis');
    expect(long).toHaveAttribute('title', longLabel);
    expect(long.getBoundingClientRect().height).toBe(28);
    expect(
      (await screen.findByTestId('enabled')).getBoundingClientRect().right
    ).toBeLessThanOrEqual(420 + 1);
  });

  it('stays inside a column narrower than its segments', async () => {
    initialize({ columnWidth: 200 });

    const group = await screen.findByTestId('enabled');
    const wrapper = group.parentElement as HTMLElement;

    expect(wrapper.getBoundingClientRect().width).toBeLessThanOrEqual(200);
    expect(wrapper.scrollWidth).toBeGreaterThan(wrapper.clientWidth);
  });

  it('does not change when disabled', async () => {
    initialize({ disabled: true });

    expect(await button('Yes')).toBeDisabled();
    expect(screen.getByTestId('value')).toHaveTextContent(/^"use_default"$/);
  });
});
