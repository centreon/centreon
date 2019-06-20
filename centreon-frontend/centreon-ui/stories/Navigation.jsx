import React from 'react';
import { storiesOf } from '@storybook/react';
import { Navigation } from '../src';
import mock from '../src/Sidebar/navigationMock';

storiesOf('Navigation', module).add(
  'Navigation - items',
  () => (
    <div style={{ width: '160px', backgroundColor: '#ededed' }}>
      <Navigation navigationData={mock} />
    </div>
  ),
  { notes: 'A very simple component' },
);
