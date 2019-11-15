import React from 'react';
import { render, fireEvent } from '@testing-library/react';
import MassiveChangeThresholds from '.';

describe('MassiveChangeThresholds', () => {
  it('massive changes with the given thresholds', () => {
    const mockConfirm = jest.fn();

    const { getAllByDisplayValue, getByText } = render(
      <MassiveChangeThresholds
        open
        labelTitle="title"
        labelCancel="cancel"
        labelConfirm="confirm"
        onCancel={jest.fn()}
        onConfirm={mockConfirm}
      />,
    );

    const inputs = getAllByDisplayValue('0');
    fireEvent.change(inputs[0], { target: { value: '80' } });
    fireEvent.change(inputs[1], { target: { value: '90' } });

    fireEvent.click(getByText('confirm').parentNode);

    expect(mockConfirm).toBeCalledWith(expect.anything(), {
      warning: '80',
      critical: '90',
    });
  });
});
