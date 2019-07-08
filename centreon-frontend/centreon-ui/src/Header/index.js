/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */
/* eslint-disable react/prefer-stateless-function */

import React, { Component } from 'react';
import classnames from 'classnames';
import styles from './header.scss';

class Header extends Component {
  render() {
    const { children, style } = this.props;
    return (
      <header className={classnames(styles.header)} style={style}>
        <div className={classnames(styles['header-inner'])}>{children}</div>
      </header>
    );
  }
}

export default Header;
