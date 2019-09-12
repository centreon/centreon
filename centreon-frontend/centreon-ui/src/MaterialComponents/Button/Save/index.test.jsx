import React from 'react';

import { render } from '@testing-library/react';

import SaveButton from '.';

describe(SaveButton, () => {
  it('renders with a floppy icon', () => {
    const { container } = render(<SaveButton />);

    expect(container.firstChild).toMatchSnapshot();
  });

  it('renders with a loading indicator when loading flag is set', () => {
    const { container } = render(<SaveButton loading />);

    expect(container.firstChild).toMatchSnapshot();
  });

  it('renders with a check icon when the suceeded flag is set', () => {
    const { container } = render(<SaveButton succeeded />);

    expect(container.firstChild).toMatchSnapshot();
  });
});
