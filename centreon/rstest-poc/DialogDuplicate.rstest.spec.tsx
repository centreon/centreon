import { describe, expect, it, rstest } from '@rstest/core';

import DialogDuplicate from '../packages/ui/src/Dialog/Duplicate';
import { fireEvent, render } from './testRender';

/**
 * Port of packages/ui/src/Dialog/Duplicate/index.test.tsx to Rstest.
 * Exercises a real MUI 7 dialog (controlled input, disabled state, callbacks)
 * to prove the MUI stack renders correctly under Rstest's Rspack bundling.
 */
const labels = {
  labelCancel: 'cancel',
  labelConfirm: 'confirm',
  labelInput: 'Duplications',
  labelTitle: 'title'
};

describe('DialogDuplicate (Rstest POC)', () => {
  it('duplicates by 1 by default', () => {
    const onConfirm = rstest.fn();

    const { getByText } = render(
      <DialogDuplicate
        open
        {...labels}
        onCancel={rstest.fn()}
        onConfirm={onConfirm}
      />
    );

    fireEvent.click(getByText('confirm'));

    expect(onConfirm).toHaveBeenCalledWith(expect.anything(), 1);
  });

  it('duplicates by the given number', () => {
    const onConfirm = rstest.fn();

    const { getByDisplayValue, getByText } = render(
      <DialogDuplicate
        open
        {...labels}
        onCancel={rstest.fn()}
        onConfirm={onConfirm}
      />
    );

    fireEvent.change(getByDisplayValue('1'), { target: { value: '3' } });
    fireEvent.click(getByText('confirm'));

    expect(onConfirm).toHaveBeenCalledWith(expect.anything(), '3');
  });

  it('disables the confirm button when the field is empty', () => {
    const { getByDisplayValue, getByText } = render(
      <DialogDuplicate
        open
        {...labels}
        onCancel={rstest.fn()}
        onConfirm={rstest.fn()}
      />
    );

    fireEvent.change(getByDisplayValue('1'), { target: { value: '' } });

    expect(getByText('confirm')).toBeDisabled();
  });
});
