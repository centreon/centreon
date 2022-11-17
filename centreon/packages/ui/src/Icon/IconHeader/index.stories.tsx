/* eslint-disable no-alert */
/* eslint-disable react/prop-types */

import React from 'react';

import DnsIcon from '@material-ui/icons/Dns';

import IconHeader from '.';

export default { title: 'Icon/Header' };

const alertOnClick = (name): void => {
  alert(`${name} clicked`);
};

const HeaderBackground = ({ children, color = undefined }): JSX.Element => (
  <div style={{ backgroundColor: color || '#232f39' }}>{children}</div>
);

export const normal = (): JSX.Element => (
  <HeaderBackground>
    <IconHeader
      Icon={DnsIcon}
      iconName="hosts"
      onClick={(): void => alertOnClick('Home')}
    />
  </HeaderBackground>
);

export const withPending = (): JSX.Element => (
  <HeaderBackground>
    <IconHeader
      pending
      Icon={DnsIcon}
      iconName="hosts"
      onClick={(): void => alertOnClick('Home')}
    />
  </HeaderBackground>
);
