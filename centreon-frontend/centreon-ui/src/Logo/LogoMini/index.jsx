/* eslint-disable react/prop-types */
/* eslint-disable jsx-a11y/click-events-have-key-events */
/* eslint-disable jsx-a11y/no-static-element-interactions */

import React from 'react';
import classnames from 'classnames';
import styles from './logo-mini.scss';
import miniLogo from '../../../img/centreon-logo-mini.svg';

function LogoMini({ customClass, onClick }) {
  return (
    <div
      onClick={onClick}
      className={classnames(styles['logo-mini'], styles[customClass || ''])}
    >
      <span>
        <img
          className={classnames(styles['logo-mini-image'])}
          src={miniLogo}
          width="23"
          height="21"
          alt=""
        />
      </span>
    </div>
  );
}

export default LogoMini;
