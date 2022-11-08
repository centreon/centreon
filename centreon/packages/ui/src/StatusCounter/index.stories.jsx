/* eslint-disable react/prop-types */

import React from 'react';

import { SeverityCode } from '../StatusChip';

import StatusCounter from '.';

export default { title: 'StatusCounter' };

const HeaderBackground = ({ children }) => (
  <div style={{ backgroundColor: '#232f39' }}>{children}</div>
);

export const severityCodeHigh = () => (
  <HeaderBackground>
    <StatusCounter count={3} severityCode={SeverityCode.High} />
  </HeaderBackground>
);

export const severityCodeMedium = () => (
  <HeaderBackground>
    <StatusCounter count={3} severityCode={SeverityCode.Medium} />
  </HeaderBackground>
);

export const severityCodeLow = () => (
  <HeaderBackground>
    <StatusCounter count={3} severityCode={SeverityCode.Low} />
  </HeaderBackground>
);

export const severityCodeOk = () => (
  <HeaderBackground>
    <StatusCounter count={3} severityCode={SeverityCode.Ok} />
  </HeaderBackground>
);

export const severityCodeHighCount0 = () => (
  <HeaderBackground>
    <StatusCounter count={0} severityCode={SeverityCode.High} />
  </HeaderBackground>
);

export const severityCodeMediumCount0 = () => (
  <HeaderBackground>
    <StatusCounter count={0} severityCode={SeverityCode.Medium} />
  </HeaderBackground>
);

export const severityCodeLowCount0 = () => (
  <HeaderBackground>
    <StatusCounter count={0} severityCode={SeverityCode.Low} />
  </HeaderBackground>
);

export const severityCodeOkCount0 = () => (
  <HeaderBackground>
    <StatusCounter count={0} severityCode={SeverityCode.Ok} />
  </HeaderBackground>
);

export const severityCodeOkBigCount = () => (
  <HeaderBackground>
    <StatusCounter count={500000} severityCode={SeverityCode.Ok} />
  </HeaderBackground>
);
