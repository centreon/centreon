/* eslint-disable react/prop-types */
/* eslint-disable jsx-a11y/click-events-have-key-events */
/* eslint-disable jsx-a11y/no-static-element-interactions */

import React from 'react';
import classnames from 'classnames';
import styles from './logo.scss';
import logo from '../../img/centreon.png';

function Logo({ customClass, onClick }) {
  return (
    <div
      onClick={onClick}
      className={classnames(styles.logo, styles[customClass || ''])}
    >
      <span>
        <img
          className={classnames(styles['logo-image'])}
          src={logo}
          width="254"
          height="57"
          alt=""
        />
      </span>
    </div>
  );
}

export default Logo;
