/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */
/* eslint-disable react/prefer-stateless-function */

import React, { Component } from 'react';
import Tabs from './Tabs';

class Tab extends Component {
  render() {
    const { children, error } = this.props;
    return <Tabs error={error}>{children}</Tabs>;
  }
}

export default Tab;
