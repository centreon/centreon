/* eslint-disable react/jsx-filename-extension */
/* eslint-disable jsx-a11y/no-static-element-interactions */
/* eslint-disable jsx-a11y/click-events-have-key-events */
/* eslint-disable react/prop-types */
/* eslint-disable react/prefer-stateless-function */

import React, { Component } from 'react';

import clsx from 'clsx';

import miniLogo from '../../../img/centreon-logo-mini.svg';

import styles from './logo-mini.scss';

class LogoMini extends Component {
  render() {
    const { customClass, onClick } = this.props;
    return (
      <div
        onClick={onClick}
        className={clsx(styles['logo-mini'], styles[customClass || ''])}
      >
        <span>
          <img
            className={clsx(styles['logo-mini-image'])}
            src={miniLogo}
            width="23"
            height="21"
            alt=""
          />
        </span>
      </div>
    );
  }
}

export default LogoMini;
