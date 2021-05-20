/* eslint-disable no-console */
/* eslint-disable react/jsx-no-duplicate-props */
/* eslint-disable react/jsx-filename-extension */
/* eslint-disable import/no-extraneous-dependencies */

import React from 'react';

import { MemoryRouter } from 'react-router-dom';

import reactMock from './reactRoutesMock';
import mock from './mock2';

import Sidebar from '.';

const replaceQueryParam = (param, newval, search) => {
  const regex = new RegExp(`([?;&])${param}[^&;]*[;&]?`);
  const query = search.replace(regex, '$1').replace(/&$/, '');

  return (
    (query.length > 2 ? `${query}&` : '?') +
    (newval ? `${param}=${newval}` : '')
  );
};

export default { title: 'Sidebar' };

export const normal = () => (
  <MemoryRouter>
    <Sidebar
      externalHistory={window}
      externalHistory={window}
      handleDirectClick={(id, url) => {
        console.log(id, url);
      }}
      navigationData={mock}
      reactRoutes={reactMock}
      onNavigate={(id) => {
        window.location = `/iframe.html${replaceQueryParam(
          'p',
          id,
          window.location.search,
        )}`;
      }}
    />
  </MemoryRouter>
);
