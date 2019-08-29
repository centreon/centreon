/* eslint-disable no-undef */

import React from 'react';
import { render } from '@testing-library/react';
import MaterialIcon from '.';

describe('MaterialIcon', () => {
  it('renders', () => {
    const { container } = render(
      <MaterialIcon>
        <i />
      </MaterialIcon>,
    );

    expect(container.firstChild).toMatchSnapshot();
  });
});

describe('Hello World', () => {
  it('renders', () => {
    const HelloWorld = () => <h1>Hello World!</h1>;

    const { container } = render(<HelloWorld />);

    expect(container.firstChild).toMatchSnapshot();
  });
});
