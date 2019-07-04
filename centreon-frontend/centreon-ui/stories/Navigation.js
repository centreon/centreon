import React from 'react';
import { storiesOf } from '@storybook/react';
import { Navigation } from '../src';
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

storiesOf('Navigation', module).add(
  'Navigation - items',
  () => (
    <div style={{ width: '160px', backgroundColor: '#ededed' }}>
      <Navigation
        navigationData={mock}
        externalHistory={window}
        reactRoutes={reactMock}
        onNavigate={(id, url) => {
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
    </div>
  ),
  { notes: 'A very simple component' },
);
