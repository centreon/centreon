import React from 'react';
import { render, fireEvent } from '@testing-library/react';
import SearchInput from '.';

describe('SearchInput', () => {
  it('renders correctly', () => {
    const { container } = render(
      <SearchInput placeholder="search" onChange={jest.fn()} />,
    );

    expect(container.firstChild).toMatchSnapshot();
  });

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
