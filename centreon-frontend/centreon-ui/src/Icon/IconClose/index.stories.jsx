import React from 'react';

import Icon from '.';

export default { title: 'Icon/Close' };

export const small = () => <Icon iconType="small" />;

export const middle = () => <Icon iconType="middle" />;

export const big = () => <Icon iconType="big" />;

export const content = () => (
  <Icon iconContentType="add" iconContentColor="green" iconType="content" />
);
