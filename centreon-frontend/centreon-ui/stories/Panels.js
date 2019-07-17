/* eslint-disable react/no-unescaped-entities */
/* eslint-disable no-dupe-keys */
/* eslint-disable no-undef */
/* eslint-disable react/jsx-filename-extension */
/* eslint-disable import/no-extraneous-dependencies */

import React from 'react';
import { storiesOf } from '@storybook/react';
import classnames from 'classnames';
import ButtonCustom from '@material-ui/core/Button';
import { Panels, Wrapper, Header, Sidebar, BAMListingPageStory } from '../src';
import styles2 from '../src/Popup/PopupNew/popup.scss';
import IconCloseNew from '../src/MaterialComponents/Icons/IconClose';
import mock from '../src/Sidebar/mock2';
import reactMock from '../src/Sidebar/reactRoutesMock';
import SubmenuHeader from '../src/Submenu/SubmenuHeader/Mocked-Submenu';
import PopupNew from '../src/Popup/PopupNew';
import BAMTableData from '../src/Pages/BAMListingMock';

storiesOf('Panels', module).add(
  'Panels',
  () => <Panels panelTtype="small" togglePanel />,
  {
    notes: 'A very simple component',
  },
);

storiesOf('Panels', module).add(
  'Panels - with header and menu',
  () => (
    <React.Fragment>
      <Wrapper style={{ alignItems: 'stretch', display: 'flex', padding: 0 }}>
        <Sidebar
          navigationData={mock}
          externalHistory={window}
          reactRoutes={reactMock}
          onNavigate={(id) => {
            window.location = `/iframe.hqtml${replaceQueryParam(
              'p',
              id,
              window.location.search,
            )}`;
          }}
          handleDirectClick={(id, url) => {
            console.log(id, url);
          }}
          style={{ height: '100vh' }}
        />
        <div
          className="content"
          style={{ display: 'flex', flexDirection: 'column', width: '100%' }}
        >
          <Header style={{ height: '56px', width: '100%', marginBottom: 20 }}>
            <SubmenuHeader submenuType="header" />
            <SubmenuHeader submenuType="header" />
            <SubmenuHeader submenuType="header" />
          </Header>
          <BAMListingPageStory BAMTableData={BAMTableData} />
        </div>
      </Wrapper>
    </React.Fragment>
  ),
  { notes: 'A very simple component' },
);

storiesOf('Panels', module).add(
  'Panels - with header, menu and validation popup',
  () => (
    <React.Fragment>
      <Wrapper style={{ alignItems: 'stretch', display: 'flex', padding: 0 }}>
        <Sidebar
          navigationData={mock}
          externalHistory={window}
          reactRoutes={reactMock}
          onNavigate={(id) => {
            window.location = `/iframe.hqtml${replaceQueryParam(
              'p',
              id,
              window.location.search,
            )}`;
          }}
          handleDirectClick={(id, url) => {
            console.log(id, url);
          }}
          style={{ height: '100vh' }}
        />
        <div
          className="content"
          style={{ display: 'flex', flexDirection: 'column', width: '100%' }}
        >
          <Header style={{ height: '56px', width: '100%', marginBottom: 20 }}>
            <SubmenuHeader submenuType="header" />
            <SubmenuHeader submenuType="header" />
            <SubmenuHeader submenuType="header" />
          </Header>
          <Panels panelTtype="small" togglePanel />
        </div>
        <PopupNew popupType="small">
          <div className={classnames(styles2['popup-header'])}>
            <h3 className={classnames(styles2['popup-title'])}>
              Changes have been made
            </h3>
          </div>
          <div className={classnames(styles2['popup-body'])}>
            <p className={classnames(styles2['popup-info'])}>
              Would you like to save before closing?
            </p>
            <ButtonCustom
              variant="contained"
              color="primary"
              style={{
                backgroundColor: '#0072CE',
                fontSize: 11,
                textAlign: 'center',
                border: '1px solid #0072CE',
              }}
            >
              SAVE
            </ButtonCustom>
            <ButtonCustom
              variant="contained"
              color="primary"
              style={{
                backgroundColor: '#0072CE',
                fontSize: 11,
                textAlign: 'center',
                marginLeft: 30,
                backgroundColor: 'transparent',
                color: '#0072CE',
                border: '1px solid #0072CE',
                boxSizing: 'border-box',
              }}
            >
              DON'T SAVE
            </ButtonCustom>
          </div>
          <IconCloseNew />
        </PopupNew>
      </Wrapper>
    </React.Fragment>
  ),
  { notes: 'A very simple component' },
);
