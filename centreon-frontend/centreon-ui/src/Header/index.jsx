/* eslint-disable react/prop-types */

import React from 'react';
import classnames from 'classnames';
import styles from './header.scss';

function Header({ children, style }) {
  return (
    <header className={classnames(styles.header)} style={style}>
      <div className={classnames(styles['header-inner'])}>{children}</div>
    </header>
  );
}

export default Header;
