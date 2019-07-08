/* eslint-disable react/jsx-no-duplicate-props */
/* eslint-disable react/jsx-filename-extension */
/* eslint-disable import/no-extraneous-dependencies */

import React from 'react';
import { storiesOf } from '@storybook/react';
import { Sidebar } from '../src';
import mock from '../src/Sidebar/mock2';
import reactMock from '../src/Sidebar/reactRoutesMock';

function replaceQueryParam(param, newval, search) {
  const regex = new RegExp(`([?;&])${param}[^&;]*[;&]?`);
  const query = search.replace(regex, '$1').replace(/&$/, '');

  return (
    (query.length > 2 ? `${query}&` : '?') +
    (newval ? `${param}=${newval}` : '')
  );
}

storiesOf('Sidebar', module).add(
  'Sidebar',
  () => (
    <Sidebar
      navigationData={mock}
      externalHistory={window}
      reactRoutes={reactMock}
      externalHistory={window}
      onNavigate={(id) => {
        window.location = `/iframe.html${replaceQueryParam(
          'p',
          id,
          window.location.search,
        )}`;
      }}
      handleDirectClick={(id, url) => {
        console.log(id, url);
      }}
    />
  ),
  { notes: 'A very simple component' },
);
