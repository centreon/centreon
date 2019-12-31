/* eslint-disable no-alert */

import React from 'react';
import ButtonAdd from '.';

export default { title: 'Button/Add' };

export const normal = () => (
  <ButtonAdd label="Add" onClick={() => alert("I've been clicked")} />
);
