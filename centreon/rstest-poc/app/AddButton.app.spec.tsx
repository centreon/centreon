import { describe, expect, it, rstest } from '@rstest/core';
import userEvent from '@testing-library/user-event';

import AddButton from '../../www/front_src/src/AgentConfiguration/Form/ConnectionInitiated/HostConfigurations/AddButton';
import { fireEvent, renderApp, screen } from './render';

/** Phase 0b port: a button component — text, icon, disabled state, callbacks. */
const defaultProps = {
  addButtonDisabled: false,
  onAddItem: (): void => undefined
};

describe('AddButton (Rstest app POC)', () => {
  it('renders the add button with its text and icon', () => {
    renderApp(<AddButton {...defaultProps} />);

    const button = screen.getByTestId('Add a host');
    expect(button).toBeVisible();
    expect(button).toHaveTextContent('Add a host');
    expect(screen.getByTestId('AddCircleIcon')).toBeVisible();
  });

  it('calls onAddItem when clicked', async () => {
    const onAddItem = rstest.fn();
    renderApp(<AddButton {...defaultProps} onAddItem={onAddItem} />);

    await userEvent.click(screen.getByTestId('Add a host'));

    expect(onAddItem).toHaveBeenCalledTimes(1);
  });

  it('disables the button when addButtonDisabled is true', () => {
    renderApp(<AddButton {...defaultProps} addButtonDisabled />);

    expect(screen.getByTestId('Add a host')).toBeDisabled();
  });

  it('does not call onAddItem when the disabled button is clicked', () => {
    const onAddItem = rstest.fn();
    renderApp(<AddButton addButtonDisabled onAddItem={onAddItem} />);

    // fireEvent bypasses the actionability check to click the disabled button.
    fireEvent.click(screen.getByTestId('Add a host'));

    expect(onAddItem).not.toHaveBeenCalled();
  });

  it('exposes an accessible aria-label', () => {
    renderApp(<AddButton {...defaultProps} />);

    expect(screen.getByTestId('Add a host')).toHaveAttribute(
      'aria-label',
      'Add a host'
    );
  });
});
