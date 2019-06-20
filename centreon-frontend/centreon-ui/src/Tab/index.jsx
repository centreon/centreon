/* eslint-disable react/prop-types */

import React from 'react';
import Tabs from './Tabs';

function Tab({ children, error }) {
  return <Tabs error={error}>{children}</Tabs>;
}

export default Tab;
