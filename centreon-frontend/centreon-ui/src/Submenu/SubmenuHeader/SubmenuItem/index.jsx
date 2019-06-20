/* eslint-disable react/prop-types */

import React from 'react';
import classnames from 'classnames';
import styles from '../submenu.scss';

function SubmenuItem({ dotColored, submenuTitle, submenuCount }) {
  return (
    <li className={classnames(styles['submenu-item'])}>
      <span
        className={classnames(styles['submenu-item-title'], {
          [styles['submenu-item-dotted']]: !!dotColored,
        })}
      >
        <span
          className={classnames(
            styles['submenu-item-dot'],
            styles[`dot-${dotColored}`],
          )}
        />
        {submenuTitle}
      </span>
      <span className={classnames(styles['submenu-item-count'])}>
        {submenuCount}
      </span>
    </li>
  );
}

export default SubmenuItem;
