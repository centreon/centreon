import React from 'react';
import { render } from '@testing-library/react';
import StepIcon from './StepIcon';

describe('StepIcon', () => {
  it('renders completed step correctly', () => {
    const { container } = render(<StepIcon completed />);

    expect(container.firstChild).toMatchSnapshot();
  });

  it('renders active step correctly', () => {
    const { container } = render(<StepIcon active icon={1} />);

    expect(container.firstChild).toMatchSnapshot();
  });

  it('renders pending step correctly', () => {
    const { container } = render(<StepIcon icon={2} />);

    expect(container.firstChild).toMatchSnapshot();
  });
});
