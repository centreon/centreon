/* eslint-disable react/prop-types */

import React from 'react';
import classnames from 'classnames';
import styles from './submenu.scss';

function SubmenuHeader({ submenuType, children, active, ...props }) {
  return (
    <div
      className={classnames(styles[`submenu-${submenuType}`], {
        [styles['submenu-active']]: !!active,
      })}
      {...props}
    >
      {children}
    </div>
  );
}

export default SubmenuHeader;
