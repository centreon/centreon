import { rs } from '@rstest/core';
import { render } from '@test-setup/render';
import { fireEvent, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { labelAddAHost } from '../../../translatedLabels';
import AddButton from './AddButton';

interface InitializeProps {
  addButtonDisabled?: boolean;
  onAddItem?: () => void;
}

const initialize = ({
  addButtonDisabled = false,
  onAddItem = (): void => undefined
}: InitializeProps): void => {
  render(
    <AddButton addButtonDisabled={addButtonDisabled} onAddItem={onAddItem} />
  );
};

describe('AddButton', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  it('renders the add button with correct text and icon', () => {
    initialize({});

    const button = screen.getByTestId(labelAddAHost);

    expect(button).toBeVisible();
    expect(button).toHaveTextContent('Add a host');
    const icon = document.querySelector('svg[data-testid="AddCircleIcon"]');
    expect(icon).toBeVisible();

    // The icon is inside the button, before its label, and the button sits
    // in a divider.
    expect(button).toContainElement(icon as HTMLElement);
    expect(button.firstElementChild).toBe(icon);
    expect(button.closest('.MuiDivider-root')).toBeInTheDocument();
  });

  it('calls onAddItem when button is clicked', async () => {
    const onAddItem = rs.fn();
    initialize({ onAddItem });

    await userEvent.click(screen.getByTestId(labelAddAHost));

    expect(onAddItem).toHaveBeenCalledTimes(1);
  });

  it('disables the button when addButtonDisabled is true', () => {
    initialize({ addButtonDisabled: true });

    expect(screen.getByTestId(labelAddAHost)).toBeDisabled();
  });

  it('enables the button when addButtonDisabled is false', () => {
    initialize({ addButtonDisabled: false });

    expect(screen.getByTestId(labelAddAHost)).toBeEnabled();
  });

  it('does not call onAddItem when disabled button is clicked', () => {
    const onAddItem = rs.fn();
    initialize({ addButtonDisabled: true, onAddItem });

    // A disabled MUI button has `pointer-events: none`, which `userEvent`
    // refuses to click through: the click is dispatched directly.
    fireEvent.click(screen.getByTestId(labelAddAHost));

    expect(onAddItem).not.toHaveBeenCalled();
  });

  it('has correct aria-label for accessibility', () => {
    initialize({});

    expect(screen.getByTestId(labelAddAHost)).toHaveAttribute(
      'aria-label',
      'Add a host'
    );
  });
});
