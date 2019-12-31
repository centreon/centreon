import React from 'react';
import { render, fireEvent } from '@testing-library/react';
import SearchInput from '.';

describe('SearchInput', () => {
  it('tiggers change', () => {
    const mockOnChange = jest.fn();

    const { getByPlaceholderText } = render(
      <SearchInput onChange={mockOnChange} />,
    );

    const input = getByPlaceholderText('Search');
    fireEvent.change(input, { target: { value: 'my search' } });

    expect(mockOnChange).toBeCalled();
  });
});
