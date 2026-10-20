import { Button } from '@mui/material';

import { rs } from '@rstest/core';
import { render } from '@test-setup/render';
import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { atom, createStore, Provider, useSetAtom } from 'jotai';

import {
  ConfirmationModal,
  type ConfirmationModalProps
} from './ConfirmationModal';

const testAtom = atom<string | null>(null);

const buttonLabel = 'Click to open modal';

const TestComponent = (args: ConfirmationModalProps<string>): JSX.Element => {
  const setAtom = useSetAtom(testAtom);

  return (
    <>
      <Button onClick={() => setAtom('John')}>{buttonLabel}</Button>
      <ConfirmationModal<string> {...args} />
    </>
  );
};

const staticLabels = {
  cancel: 'Cancel',
  confirm: 'Confirm',
  description: 'Description',
  title: 'Title'
};

const dynamicLabels = {
  cancel: 'Cancel',
  confirm: 'Confirm',
  description: (data: string | null): string => `Description ${data}`,
  title: (data: string | null): string => `Hello ${data}`
};

type InitializeProps = Pick<
  ConfirmationModalProps<string>,
  'labels' | 'disabled' | 'hasCloseButton' | 'isDanger'
>;

interface InitializeResult {
  cancel: ReturnType<typeof rs.fn>;
  confirm: ReturnType<typeof rs.fn>;
}

const initialize = (props: InitializeProps): InitializeResult => {
  const store = createStore();

  const cancel = rs.fn();
  const confirm = rs.fn();

  render(
    <Provider store={store}>
      <TestComponent
        {...props}
        atom={testAtom}
        onCancel={cancel}
        onConfirm={confirm}
      />
    </Provider>
  );

  return {
    cancel,
    confirm
  };
};

const openModal = async (): Promise<void> => {
  await userEvent.click(screen.getByText(buttonLabel));
};

// What every open modal of the former visual snapshots shows: the title, the
// description, then the Cancel and Confirm buttons, in that order.
const expectModalContent = async ({
  description,
  title
}: {
  description: string;
  title: string;
}): Promise<void> => {
  const dialog = await screen.findByRole('dialog');

  expect(screen.getByTestId('modal-header')).toHaveTextContent(
    new RegExp(`^${title}$`)
  );
  expect(dialog).toHaveTextContent(description);
  expect(screen.getByTestId('cancel')).toBeVisible();
  expect(screen.getByTestId('cancel')).toHaveTextContent(/^Cancel$/);
  expect(screen.getByTestId('confirm')).toBeVisible();
  expect(screen.getByTestId('confirm')).toHaveTextContent(/^Confirm$/);
  expect(
    screen
      .getByTestId('cancel')
      .compareDocumentPosition(screen.getByTestId('confirm'))
  ).toBe(Node.DOCUMENT_POSITION_FOLLOWING);
};

const expectModalClosed = async (): Promise<void> => {
  await waitFor(() => {
    expect(screen.queryByText('Title')).not.toBeInTheDocument();
  });
  expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  expect(screen.getByText(buttonLabel)).toBeVisible();
};

describe('Confirmation modal', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  it('displays the modal with static labels', async () => {
    initialize({ labels: staticLabels });

    await openModal();

    expect(await screen.findByText('Title')).toBeVisible();
    expect(screen.getByText('Description')).toBeVisible();
    expect(screen.getByLabelText('close')).toBeVisible();

    await expectModalContent({ description: 'Description', title: 'Title' });
    expect(screen.getByTestId('confirm')).toBeEnabled();
    expect(screen.getByTestId('confirm')).toHaveAttribute(
      'data-is-danger',
      'false'
    );
  });

  it('displays the modal with dynamic labels', async () => {
    initialize({ labels: dynamicLabels });

    await openModal();

    expect(await screen.findByText('Hello John')).toBeVisible();
    expect(screen.getByText('Description John')).toBeVisible();

    await expectModalContent({
      description: 'Description John',
      title: 'Hello John'
    });
    expect(screen.getByLabelText('close')).toBeVisible();
  });

  it('displays the confirm button as disabled when a prop is set', async () => {
    initialize({ disabled: true, labels: staticLabels });

    await openModal();

    expect(await screen.findByText('Confirm')).toBeVisible();
    expect(screen.getByTestId('confirm')).toBeDisabled();

    await expectModalContent({ description: 'Description', title: 'Title' });
    expect(screen.getByTestId('cancel')).toBeEnabled();
  });

  it('displays the confirm button as danger when a prop is set', async () => {
    initialize({ isDanger: true, labels: staticLabels });

    await openModal();

    expect(await screen.findByText('Confirm')).toBeVisible();
    expect(screen.getByTestId('confirm')).toHaveAttribute(
      'data-is-danger',
      'true'
    );

    await expectModalContent({ description: 'Description', title: 'Title' });

    // The snapshot showed a red Confirm button: its background is the
    // theme's error colour.
    const errorColorProbe = document.createElement('div');
    errorColorProbe.style.backgroundColor = 'var(--color-error-main)';
    document.body.appendChild(errorColorProbe);
    const errorColor = getComputedStyle(errorColorProbe).backgroundColor;
    errorColorProbe.remove();

    expect(errorColor).not.toBe('rgba(0, 0, 0, 0)');
    expect(
      getComputedStyle(screen.getByTestId('confirm')).backgroundColor
    ).toBe(errorColor);
  });

  it('displays the modal without the close button when a prop is set', async () => {
    initialize({ hasCloseButton: false, labels: staticLabels });

    await openModal();

    await expectModalContent({ description: 'Description', title: 'Title' });
    expect(screen.queryByLabelText('close')).not.toBeInTheDocument();
  });

  it('closes the modal when the close button is clicked', async () => {
    initialize({ labels: staticLabels });

    await openModal();

    expect(await screen.findByText('Title')).toBeVisible();

    await userEvent.click(screen.getByLabelText('close'));

    await expectModalClosed();
  });

  it('closes the modal when the cancel button is clicked', async () => {
    const { cancel } = initialize({ labels: staticLabels });

    await openModal();

    expect(await screen.findByText('Title')).toBeVisible();

    await userEvent.click(screen.getByText('Cancel'));

    expect(cancel).toHaveBeenCalledWith('John');

    await expectModalClosed();
  });

  it('closes the modal when the confirm button is clicked', async () => {
    const { confirm } = initialize({ labels: staticLabels });

    await openModal();

    expect(await screen.findByText('Title')).toBeVisible();

    await userEvent.click(screen.getByText('Confirm'));

    expect(confirm).toHaveBeenCalledWith('John');

    await expectModalClosed();
  });
});
