/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */
/* eslint-disable react/prefer-stateless-function */

import React, { Component } from 'react';
import clsx from 'clsx';
import styles from '../submenu.scss';

class SubmenuItems extends Component {
  render() {
    const { children } = this.props;
    return <ul className={clsx(styles['submenu-items'])}>{children}</ul>;
  }
}

export default SubmenuItems;
