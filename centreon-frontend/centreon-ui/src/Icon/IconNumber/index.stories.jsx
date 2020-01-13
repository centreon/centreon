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
    <IconNumber iconNumber={3} iconColor="red" iconType="colored" />
  </HeaderBackground>
);

export const coloredGrayLight = () => (
  <HeaderBackground>
    <IconNumber iconNumber={3} iconColor="gray-light" iconType="colored" />
  </HeaderBackground>
);

export const coloredGrayDark = () => (
  <HeaderBackground>
    <IconNumber iconNumber={3} iconColor="gray-dark" iconType="colored" />
  </HeaderBackground>
);

export const coloredGreen = () => (
  <HeaderBackground>
    <IconNumber iconNumber={3} iconColor="green" iconType="colored" />
  </HeaderBackground>
);

export const coloredOrange = () => (
  <HeaderBackground>
    <IconNumber iconNumber={3} iconColor="orange" iconType="colored" />
  </HeaderBackground>
);

export const coloredBlue = () => (
  <HeaderBackground>
    <IconNumber iconNumber={3} iconColor="blue" iconType="colored" />
  </HeaderBackground>
);

export const borderedRed = () => (
  <HeaderBackground>
    <IconNumber iconNumber={3} iconColor="red" iconType="bordered" />
  </HeaderBackground>
);

export const borderedGrayLight = () => (
  <HeaderBackground>
    <IconNumber iconNumber={3} iconColor="gray-light" iconType="bordered" />
  </HeaderBackground>
);

export const borderedGrayDark = () => (
  <HeaderBackground>
    <IconNumber iconNumber={3} iconColor="gray-dark" iconType="bordered" />
  </HeaderBackground>
);

export const borderedGreen = () => (
  <HeaderBackground>
    <IconNumber iconNumber={3} iconColor="green" iconType="bordered" />
  </HeaderBackground>
);

export const borderedOrange = () => (
  <HeaderBackground>
    <IconNumber iconNumber={3} iconColor="orange" iconType="bordered" />
  </HeaderBackground>
);

export const borderedBlue = () => (
  <HeaderBackground>
    <IconNumber iconNumber={3} iconColor="blue" iconType="bordered" />
  </HeaderBackground>
);

export const bigNumber = () => (
  <HeaderBackground>
    <IconNumber iconNumber={123456789} iconColor="blue" iconType="bordered" />
  </HeaderBackground>
);
