/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */
/* eslint-disable react/prefer-stateless-function */

import React, { Component } from 'react';
import classnames from 'classnames';
import styles from '../submenu.scss';

class SubmenuItems extends Component {
  render() {
    const { children } = this.props;
    return <ul className={classnames(styles['submenu-items'])}>{children}</ul>;
  }
}

export default SubmenuItems;
