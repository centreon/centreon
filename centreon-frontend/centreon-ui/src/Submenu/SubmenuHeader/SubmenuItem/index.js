/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */
/* eslint-disable react/prefer-stateless-function */

import React, { Component } from 'react';
import clsx from 'clsx';
import styles from '../submenu.scss';

class SubmenuItem extends Component {
  render() {
    const { dotColored, submenuTitle, submenuCount } = this.props;
    return (
      <li className={clsx(styles['submenu-item'])}>
        <span
          className={clsx(styles['submenu-item-title'], {
            [styles['submenu-item-dotted']]: !!dotColored,
          })}
        >
          <span
            className={clsx(
              styles['submenu-item-dot'],
              styles[`dot-${dotColored}`],
            )}
          />
          {submenuTitle}
        </span>
        <span className={clsx(styles['submenu-item-count'])}>
          {submenuCount}
        </span>
      </li>
    );
  }
}

export default SubmenuItem;
