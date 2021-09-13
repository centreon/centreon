/* eslint-disable no-alert */
/* eslint-disable react/prop-types */

import React from 'react';

import DnsIcon from '@material-ui/icons/Dns';

import IconHeader from '.';

export default { title: 'Icon/Header' };

const alertOnClick = (name) => {
  alert(`${name} clicked`);
};

const HeaderBackground = ({ children, color }) => (
  <div style={{ backgroundColor: color || '#232f39' }}>{children}</div>
);

export const normal = () => (
  <HeaderBackground>
    <IconHeader
      Icon={DnsIcon}
      iconName="hosts"
      onClick={() => alertOnClick('Home')}
    />
  </HeaderBackground>
);

export const withPending = () => (
  <HeaderBackground>
    <IconHeader
      pending
      Icon={DnsIcon}
      iconName="hosts"
      onClick={() => alertOnClick('Home')}
    />
  </HeaderBackground>
);
