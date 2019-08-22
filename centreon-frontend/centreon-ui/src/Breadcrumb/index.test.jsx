/* eslint-disable no-undef */

import React from 'react';
import { render } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import Breadcrumb from '.';

describe('Breadcrumb', () => {
  it('renders', () => {
    const breadcrumbs = [
      {
        label: 'first level',
        link: '/firstLevel',
      },
      {
        label: 'second level',
        link: '/secondLevel',
      },
    ];
    const { container } = render(
      <MemoryRouter>
        <Breadcrumb breadcrumbs={breadcrumbs} />
      </MemoryRouter>,
    );

    expect(container.firstChild).toMatchSnapshot();
  });
});
