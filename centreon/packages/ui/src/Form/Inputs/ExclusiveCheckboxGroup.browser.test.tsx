import { Typography } from '@mui/material';

import { rs } from '@rstest/core';
import { render } from '@test-setup/render';
import { fireEvent, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
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

interface InitializeProps {
  disabled?: boolean;
  notificationOptions?: Array<string> | null;
  variant?: 'checkboxes' | 'chips';
}

const initialize = ({
  notificationOptions = [],
  disabled = false,
  variant
}: InitializeProps = {}): void => {
  render(
    <Form
      initialValues={{ notificationOptions }}
      inputs={[
        {
          dataTestId: 'notification-options',
          exclusiveCheckboxGroup: {
            direction: 'horizontal',
            exclusiveLabel: 'No notifications',
            exclusiveOption: 'None',
            options,
            variant
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
      submit={rs.fn()}
      validationSchema={object()}
    />
  );
};

const toggle = (): Promise<HTMLElement> =>
  screen.findByLabelText('No notifications');

const checkbox = (option: string): HTMLElement =>
  screen.getByRole('checkbox', { name: option });

const expectValue = async (value: Array<string> | null): Promise<void> => {
  await waitFor(() => {
    expect(screen.getByTestId('value')).toHaveTextContent(
      new RegExp(`^${JSON.stringify(value).replace(/[[\]]/g, '\\$&')}$`)
    );
  });
};

describe('Exclusive checkbox group', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  it('displays the toggle and every option except the exclusive one', async () => {
    initialize();

    expect(await toggle()).not.toBeChecked();
    expect(screen.getByLabelText('Down')).toBeInTheDocument();
    expect(screen.getByLabelText('Unreachable')).toBeInTheDocument();
    expect(screen.getByLabelText('Recovery')).toBeInTheDocument();
    expect(screen.queryByLabelText('None')).not.toBeInTheDocument();

    // Replaces the snapshot: the three options in order, then the toggle,
    // all unticked and usable, and the empty value.
    expect(
      screen
        .getAllByRole('checkbox')
        .map((element) => element.getAttribute('aria-label') ?? element.id)
    ).toEqual(['Down', 'Unreachable', 'Recovery', 'No notifications']);
    ['Down', 'Unreachable', 'Recovery'].forEach((option) => {
      expect(checkbox(option)).not.toBeChecked();
      expect(checkbox(option)).toBeEnabled();
    });
    expect(await toggle()).toBeEnabled();
    expect(screen.getByText('No notifications')).toBeVisible();
    await expectValue([]);
  });

  it('keeps the inherited state when nothing is selected', async () => {
    initialize();

    expect(await toggle()).not.toBeChecked();
    expect(screen.getByLabelText('Down')).not.toBeChecked();
    expect(screen.getByLabelText('Down')).toBeEnabled();
    await expectValue([]);
  });

  it('treats a null value as the inherited state', async () => {
    initialize({ notificationOptions: null });

    expect(await toggle()).not.toBeChecked();
    expect(screen.getByLabelText('Down')).not.toBeChecked();
    expect(screen.getByLabelText('Down')).toBeEnabled();
  });

  it('displays the exclusive state from the initial value', async () => {
    initialize({ notificationOptions: ['None'] });

    expect(await toggle()).toBeChecked();
    expect(screen.getByLabelText('Down')).not.toBeChecked();
    expect(screen.getByLabelText('Down')).toBeDisabled();
    await expectValue(['None']);

    // Replaces the snapshot: every option is unticked and greyed out.
    ['Unreachable', 'Recovery'].forEach((option) => {
      expect(checkbox(option)).not.toBeChecked();
      expect(checkbox(option)).toBeDisabled();
    });
    expect(await toggle()).toBeEnabled();
  });

  it('displays a selection from the initial value', async () => {
    initialize({ notificationOptions: ['Down', 'Recovery'] });

    expect(await toggle()).not.toBeChecked();
    expect(screen.getByLabelText('Down')).toBeChecked();
    expect(screen.getByLabelText('Unreachable')).not.toBeChecked();
    expect(screen.getByLabelText('Recovery')).toBeChecked();
    await expectValue(['Down', 'Recovery']);
  });

  it('goes from the inherited state to a selection and back', async () => {
    initialize();

    await userEvent.click(await screen.findByLabelText('Down'));
    await userEvent.click(screen.getByLabelText('Recovery'));
    await expectValue(['Down', 'Recovery']);

    await userEvent.click(screen.getByLabelText('Down'));
    await userEvent.click(screen.getByLabelText('Recovery'));
    await expectValue([]);
    expect(await toggle()).not.toBeChecked();
  });

  it('goes from the inherited state to the exclusive state and back', async () => {
    initialize();

    await userEvent.click(await toggle());
    await waitFor(async () => {
      expect(await toggle()).toBeChecked();
    });
    await expectValue(['None']);

    await userEvent.click(await toggle());
    await waitFor(async () => {
      expect(await toggle()).not.toBeChecked();
    });
    await expectValue([]);
  });

  it('clears the selection when the toggle is turned on and leaves it empty when turned off', async () => {
    initialize({ notificationOptions: ['Down', 'Recovery'] });

    await userEvent.click(await toggle());
    await expectValue(['None']);
    await waitFor(() => {
      expect(screen.getByLabelText('Down')).not.toBeChecked();
    });
    expect(screen.getByLabelText('Down')).toBeDisabled();
    expect(screen.getByLabelText('Recovery')).not.toBeChecked();
    expect(screen.getByLabelText('Recovery')).toBeDisabled();

    await userEvent.click(await toggle());
    await expectValue([]);
    await waitFor(() => {
      expect(screen.getByLabelText('Down')).toBeEnabled();
    });
    expect(screen.getByLabelText('Down')).not.toBeChecked();

    await userEvent.click(screen.getByLabelText('Unreachable'));
    await expectValue(['Unreachable']);
  });

  it('prevents ticking a box while the toggle is on', async () => {
    initialize({ notificationOptions: ['None'] });

    // The disabled checkbox has `pointer-events: none`, which `userEvent`
    // refuses: `fireEvent` forces the click, as `click({ force: true })` did.
    fireEvent.click(await screen.findByLabelText('Down'));
    expect(screen.getByLabelText('Down')).not.toBeChecked();
    await expectValue(['None']);
  });

  it('disables both the toggle and the checkboxes when the input is disabled', async () => {
    initialize({ disabled: true, notificationOptions: ['Down'] });

    expect(await toggle()).toBeDisabled();
    expect(screen.getByLabelText('Down')).toBeChecked();
    expect(screen.getByLabelText('Down')).toBeDisabled();
    await expectValue(['Down']);
  });
});

describe('Exclusive checkbox group, as chips', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  const chip = (option: string): Promise<HTMLElement> =>
    screen.findByTestId(`notification-options-${option}`);

  it('displays the options as chips and the exclusive one as the toggle', async () => {
    initialize({ variant: 'chips' });

    for (const option of ['Down', 'Unreachable', 'Recovery']) {
      expect(await chip(option)).toHaveAttribute('aria-pressed', 'false');
    }
    expect(
      screen.queryByTestId('notification-options-None')
    ).not.toBeInTheDocument();
    expect(await toggle()).not.toBeChecked();
    expect(
      screen.queryByRole('checkbox', { name: 'Down' })
    ).not.toBeInTheDocument();
  });

  it('toggles a chip on and off', async () => {
    initialize({ variant: 'chips' });

    await userEvent.click(await chip('Down'));
    await userEvent.click(await chip('Recovery'));

    await expectValue(['Down', 'Recovery']);
    await waitFor(async () => {
      expect(await chip('Down')).toHaveAttribute('aria-pressed', 'true');
    });

    await userEvent.click(await chip('Down'));

    await expectValue(['Recovery']);
  });

  it('clears and disables the chips while the toggle is on', async () => {
    initialize({ notificationOptions: ['Down', 'Recovery'], variant: 'chips' });

    await userEvent.click(await toggle());

    await expectValue(['None']);
    await waitFor(async () => {
      expect(await chip('Down')).toHaveAttribute('aria-pressed', 'false');
    });
    expect(await chip('Down')).toHaveAttribute('aria-disabled', 'true');
  });

  it('leaves the chips empty and usable once the toggle is off', async () => {
    initialize({ notificationOptions: ['None'], variant: 'chips' });

    await userEvent.click(await toggle());

    await expectValue([]);
    await waitFor(async () => {
      expect(await chip('Unreachable')).not.toHaveAttribute(
        'aria-disabled',
        'true'
      );
    });
    await userEvent.click(await chip('Unreachable'));

    await expectValue(['Unreachable']);
  });

  it('disables the chips and the toggle when the input is disabled', async () => {
    initialize({ disabled: true, variant: 'chips' });

    expect(await chip('Down')).toHaveAttribute('aria-disabled', 'true');
    expect(await toggle()).toBeDisabled();
  });
});
