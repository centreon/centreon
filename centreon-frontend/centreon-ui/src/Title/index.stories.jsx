import React from 'react';

import Title from '.';

export default { title: 'Title' };

export const normal = () => <Title label="Test" />;

export const host = () => <Title titleColor="host" label="Host" />;

export const object = () => <Title label="Test" icon="object" />;

export const puzzle = () => (
  <Title label="Test" icon="puzzle" titleColor="blue" />
);
