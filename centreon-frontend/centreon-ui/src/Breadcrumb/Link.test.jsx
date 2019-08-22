/* eslint-disable no-undef */

import React from 'react';
import { render } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import BreadcrumbLink from './Link';

describe('Breadcrumb', () => {
  it('renders', () => {
    const breadcrumb = {
      label: 'first level',
      link: '/firstLevel',
    };

    const { container } = render(
      <MemoryRouter>
        <BreadcrumbLink index={0} count={1} breadcrumb={breadcrumb} />
      </MemoryRouter>,
    );

    expect(container.firstChild).toMatchSnapshot();
  });
});
