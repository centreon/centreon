import { rs } from '@rstest/core';
import { render } from '@test-setup/render';
import { waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import NumberField, { type NumberProps } from './Number';

const initialize = (props: NumberProps): void => {
  render(<NumberField {...props} />);
};

const getInput = (): HTMLInputElement =>
  document.querySelector('input') as HTMLInputElement;

const getFieldWidth = (): number =>
  (
    getInput().closest('.MuiTextField-root') as HTMLElement
  ).getBoundingClientRect().width;

describe('Number field', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  it('displays the placeholder when the field has no default or fallback values', () => {
    initialize({ dataTestId: 'test', onChange: rs.fn() });

    expect(getInput()).toHaveValue(null);
    expect(getInput()).toHaveAttribute('placeholder', '0');
  });

  it('displays the fallback value as placeholder when the field as no default value', () => {
    initialize({ dataTestId: 'test', fallbackValue: 25, onChange: rs.fn() });

    expect(getInput()).toHaveValue(null);
    expect(getInput()).toHaveAttribute('placeholder', '25');
  });

  it('displays the default value as placeholder when the field as default value', () => {
    initialize({ dataTestId: 'test', defaultValue: 25, onChange: rs.fn() });

    expect(getInput()).toHaveValue(25);
    // The snapshot shows the value only: no placeholder behind it.
    expect(getInput()).not.toHaveAttribute('placeholder');
  });

  it('displays the fallback value when the field is cleared out', async () => {
    const onChange = rs.fn();

    initialize({
      dataTestId: 'test',
      defaultValue: 25,
      fallbackValue: 10,
      onChange
    });

    expect(getInput()).toHaveValue(25);
    await userEvent.clear(getInput());
    await waitFor(() => {
      expect(getInput()).toHaveValue(null);
    });
    expect(getInput()).toHaveAttribute('placeholder', '10');
    expect(onChange).toHaveBeenLastCalledWith(10);
  });

  it('displays the field using auto size', async () => {
    initialize({
      autoSize: true,
      dataTestId: 'test',
      defaultValue: 25,
      onChange: rs.fn()
    });

    expect(getInput()).toHaveValue(25);

    await userEvent.type(getInput(), '0');

    await waitFor(() => {
      expect(getInput()).toHaveValue(250);
    });
    // The snapshot showed a narrow field sized on its value, where a field
    // without auto size has the 178px default width.
    expect(getFieldWidth()).toBeLessThan(100);
  });

  it('sets the value to the minimum value configured when a value less than the minimum value is typed in the field', async () => {
    initialize({
      dataTestId: 'test',
      defaultValue: 25,
      onChange: rs.fn(),
      textFieldSlotsAndSlotProps: {
        slotProps: {
          htmlInput: {
            min: 2
          }
        }
      }
    });

    expect(getInput()).toHaveValue(25);

    await userEvent.clear(getInput());
    await userEvent.type(getInput(), '-1');

    await waitFor(() => {
      expect(getInput()).toHaveValue(2);
    });
  });
});
