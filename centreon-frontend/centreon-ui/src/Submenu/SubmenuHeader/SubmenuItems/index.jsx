/* eslint-disable react/prop-types */

import React from 'react';
import classnames from 'classnames';
import styles from '../submenu.scss';

function SubmenuItems({ children }) {
  return <ul className={classnames(styles['submenu-items'])}>{children}</ul>;
}

export default SubmenuItems;
