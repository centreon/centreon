import React from 'react';
import { render, fireEvent } from '@testing-library/react';
import DialogDuplicate from '.';

describe('DialogDuplicate', () => {
  it('duplicates by 1 by default', () => {
    const mockConfirm = jest.fn();

    const { getByText } = render(
      <DialogDuplicate
        open
        labelTitle="title"
        labelInput="Duplications"
        labelCancel="cancel"
        labelConfirm="confirm"
        onCancel={jest.fn()}
        onConfirm={mockConfirm}
      />,
    );

    fireEvent.click(getByText('confirm').parentNode);

    expect(mockConfirm).toBeCalledWith(expect.anything(), 1);
  });

  it('duplicates by the given number', () => {
    const mockConfirm = jest.fn();

    const { getByDisplayValue, getByText } = render(
      <DialogDuplicate
        open
        labelTitle="title"
        labelInput="Duplications"
        labelCancel="cancel"
        labelConfirm="confirm"
        onCancel={jest.fn()}
        onConfirm={mockConfirm}
      />,
    );

    const input = getByDisplayValue('1');
    fireEvent.change(input, { target: { value: '3' } });

    fireEvent.click(getByText('confirm').parentNode);

    expect(mockConfirm).toBeCalledWith(expect.anything(), '3');
  });
});
