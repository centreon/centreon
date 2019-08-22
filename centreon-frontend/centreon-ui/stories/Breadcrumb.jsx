/* eslint-disable import/no-extraneous-dependencies */

import React from 'react';
import { storiesOf } from '@storybook/react';
import { MemoryRouter } from 'react-router-dom';
import { Breadcrumb } from '../src';

storiesOf('Breadcrumb', module).add('with three levels', () => (
  <MemoryRouter>
    <Breadcrumb
      breadcrumbs={[
        {
          label: 'first level',
          link: '#',
        },
        {
          label: 'second level',
          link: '#',
        },
        {
          label: 'third level',
          link: '#',
        },
      ]}
    />
  </MemoryRouter>
));
