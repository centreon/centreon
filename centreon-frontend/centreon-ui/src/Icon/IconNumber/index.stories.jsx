/* eslint-disable react/prop-types */

import React from 'react';

import IconNumber from '.';

export default { title: 'Icon/Number' };

const HeaderBackground = ({ children }) => (
  <div style={{ backgroundColor: '#232f39' }}>{children}</div>
);

export const normal = () => (
  <HeaderBackground>
    <IconNumber iconNumber={3} />
  </HeaderBackground>
);

export const coloredRed = () => (
  <HeaderBackground>
    <IconNumber iconColor="red" iconNumber={3} iconType="colored" />
  </HeaderBackground>
);

export const coloredGrayLight = () => (
  <HeaderBackground>
    <IconNumber iconColor="gray-light" iconNumber={3} iconType="colored" />
  </HeaderBackground>
);

export const coloredGrayDark = () => (
  <HeaderBackground>
    <IconNumber iconColor="gray-dark" iconNumber={3} iconType="colored" />
  </HeaderBackground>
);

export const coloredGreen = () => (
  <HeaderBackground>
    <IconNumber iconColor="green" iconNumber={3} iconType="colored" />
  </HeaderBackground>
);

export const coloredOrange = () => (
  <HeaderBackground>
    <IconNumber iconColor="orange" iconNumber={3} iconType="colored" />
  </HeaderBackground>
);

export const coloredBlue = () => (
  <HeaderBackground>
    <IconNumber iconColor="blue" iconNumber={3} iconType="colored" />
  </HeaderBackground>
);

export const borderedRed = () => (
  <HeaderBackground>
    <IconNumber iconColor="red" iconNumber={3} iconType="bordered" />
  </HeaderBackground>
);

export const borderedGrayLight = () => (
  <HeaderBackground>
    <IconNumber iconColor="gray-light" iconNumber={3} iconType="bordered" />
  </HeaderBackground>
);

export const borderedGrayDark = () => (
  <HeaderBackground>
    <IconNumber iconColor="gray-dark" iconNumber={3} iconType="bordered" />
  </HeaderBackground>
);

export const borderedGreen = () => (
  <HeaderBackground>
    <IconNumber iconColor="green" iconNumber={3} iconType="bordered" />
  </HeaderBackground>
);

export const borderedOrange = () => (
  <HeaderBackground>
    <IconNumber iconColor="orange" iconNumber={3} iconType="bordered" />
  </HeaderBackground>
);

export const borderedBlue = () => (
  <HeaderBackground>
    <IconNumber iconColor="blue" iconNumber={3} iconType="bordered" />
  </HeaderBackground>
);

export const bigNumber = () => (
  <HeaderBackground>
    <IconNumber iconColor="blue" iconNumber={123456789} iconType="bordered" />
  </HeaderBackground>
);
