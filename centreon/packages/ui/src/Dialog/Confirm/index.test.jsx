import React from 'react';

import { render, fireEvent } from '@testing-library/react';

import DialogConfirm from '.';

describe('DialogConfirm', () => {
  it('confirms', () => {
    const mockConfirm = jest.fn();

    const { getByText } = render(
      <DialogConfirm
        open
        labelCancel="cancel"
        labelConfirm="confirm"
        labelMessage="message"
        labelTitle="title"
        onCancel={jest.fn()}
        onConfirm={mockConfirm}
      />,
    );

    fireEvent.click(getByText('confirm').parentNode);

    expect(mockConfirm).toBeCalled();
  });

  it('cancels', () => {
    const mockCancel = jest.fn();

    const { getByText } = render(
      <DialogConfirm
        open
        labelCancel="cancel"
        labelConfirm="confirm"
        labelMessage="message"
        labelTitle="title"
        onCancel={mockCancel}
        onConfirm={jest.fn()}
      />,
    );

    fireEvent.click(getByText('cancel').parentNode);

    expect(mockCancel).toBeCalled();
  });
});
