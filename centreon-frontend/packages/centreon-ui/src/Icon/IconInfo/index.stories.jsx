import React from 'react';

import Icon from '.';

export default { title: 'Icon/Info' };

export const state = () => <Icon iconName="state" />;

export const question = () => <Icon iconName="question" />;

export const questionWithText = () => (
  <Icon iconName="question" iconText="Test" />
);

export const content = () => (
  <Icon iconContentColor="green" iconContentType="add" iconType="content" />
);
