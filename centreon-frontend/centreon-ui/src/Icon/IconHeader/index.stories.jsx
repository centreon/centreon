/* eslint-disable no-alert */
/* eslint-disable react/prop-types */

import React from 'react';

import IconHeader from '.';

export default { title: 'Icon/Header' };

const customStyle = {
  display: 'block',
  marginTop: '20px',
  paddingTop: '20px',
  width: '50px',
};

const alertOnClick = (name) => {
  alert(`${name} clicked`);
};

const HeaderBackground = ({ children, color }) => (
  <div style={{ backgroundColor: color || '#232f39' }}>{children}</div>
);

export const home = () => (
  <HeaderBackground>
    <IconHeader iconType="home" onClick={() => alertOnClick('Home')} />
  </HeaderBackground>
);
export const monitoring = () => (
  <HeaderBackground>
    <IconHeader
      iconType="monitoring"
      onClick={() => alertOnClick('Monitoring')}
    />
  </HeaderBackground>
);
export const reporting = () => (
  <HeaderBackground>
    <IconHeader
      iconType="reporting"
      onClick={() => alertOnClick('HoReportingme')}
    />
  </HeaderBackground>
);
export const configuration = () => (
  <HeaderBackground>
    <IconHeader
      iconType="configuration"
      onClick={() => alertOnClick('Configuration')}
    />
  </HeaderBackground>
);
export const administration = () => (
  <HeaderBackground color="#BDC0C3">
    <IconHeader
      iconType="administration"
      onClick={() => alertOnClick('Administration')}
    />
  </HeaderBackground>
);
export const poller = () => (
  <HeaderBackground>
    <IconHeader iconType="poller" onClick={() => alertOnClick('Poller')} />
  </HeaderBackground>
);
export const hosts = () => (
  <HeaderBackground>
    <IconHeader iconType="hosts" onClick={() => alertOnClick('Hosts')} />
  </HeaderBackground>
);
export const services = () => (
  <HeaderBackground>
    <IconHeader iconType="services" onClick={() => alertOnClick('Services')} />
  </HeaderBackground>
);
export const topCounter = () => (
  <HeaderBackground>
    <IconHeader
      iconType="top-counter"
      onClick={() => alertOnClick('Top counter')}
    />
  </HeaderBackground>
);
export const user = () => (
  <HeaderBackground>
    <IconHeader iconType="user" onClick={() => alertOnClick('User')} />
  </HeaderBackground>
);

export const homeWithText = () => (
  <HeaderBackground>
    <IconHeader
      iconName="Home"
      iconType="home"
      onClick={() => alertOnClick('Home')}
    />
  </HeaderBackground>
);

export const homeWithCustomStyle = () => (
  <HeaderBackground>
    <IconHeader
      iconType="home"
      style={customStyle}
      onClick={() => alertOnClick('Home')}
    />
  </HeaderBackground>
);

export const homeWithChildren = () => (
  <HeaderBackground>
    <IconHeader iconType="home" onClick={() => alertOnClick('Home')}>
      <p style={{ color: 'white' }}>
        Hey!
        <br />
        I&apos;m a children node
      </p>
    </IconHeader>
  </HeaderBackground>
);
