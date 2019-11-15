import React from 'react';
import { render, fireEvent } from '@testing-library/react';
import ActionBar from './ActionBar';

describe('ActionBar', () => {
  it('renders correctly', () => {
    const { container } = render(<ActionBar />);

    expect(container.firstChild).toMatchSnapshot();
  });

  it('cancels', () => {
    const mockCancel = jest.fn();

    const { getByText } = render(
      <ActionBar onCancel={mockCancel} labelCancel="Exit" />,
    );

    fireEvent.click(getByText('Exit').parentNode);

    expect(mockCancel).toBeCalled();
  });
});
