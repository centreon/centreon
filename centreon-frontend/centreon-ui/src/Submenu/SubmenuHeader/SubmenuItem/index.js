/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */
/* eslint-disable react/prefer-stateless-function */

import React, { Component } from 'react';
import classnames from 'classnames';
import styles from '../submenu.scss';

class SubmenuItem extends Component {
  render() {
    const { dotColored, submenuTitle, submenuCount } = this.props;
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
}

export default SubmenuItem;
