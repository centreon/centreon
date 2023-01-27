import { render, fireEvent } from '../../testRenderer';

import DialogDuplicate from '.';

describe('DialogDuplicate', () => {
  it('duplicates by 1 by default', () => {
    const mockConfirm = jest.fn();

    const { getByText } = render(
      <DialogDuplicate
        open
        labelCancel="cancel"
        labelConfirm="confirm"
        labelInput="Duplications"
        labelTitle="title"
        onCancel={jest.fn()}
        onConfirm={mockConfirm}
      />
    );

    fireEvent.click(getByText('confirm'));

    expect(mockConfirm).toBeCalledWith(expect.anything(), 1);
  });

  it('duplicates by the given number', () => {
    const mockConfirm = jest.fn();

    const { getByDisplayValue, getByText } = render(
      <DialogDuplicate
        open
        labelCancel="cancel"
        labelConfirm="confirm"
        labelInput="Duplications"
        labelTitle="title"
        onCancel={jest.fn()}
        onConfirm={mockConfirm}
      />
    );

    const input = getByDisplayValue('1');
    fireEvent.change(input, { target: { value: '3' } });

    fireEvent.click(getByText('confirm'));

    expect(mockConfirm).toBeCalledWith(expect.anything(), '3');
  });
});
