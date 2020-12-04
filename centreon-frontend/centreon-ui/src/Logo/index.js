/* eslint-disable react/jsx-filename-extension */
/* eslint-disable jsx-a11y/no-static-element-interactions */
/* eslint-disable jsx-a11y/click-events-have-key-events */
/* eslint-disable react/destructuring-assignment */
/* eslint-disable react/prop-types */
/* eslint-disable react/prefer-stateless-function */

import React, { Component } from 'react';

import clsx from 'clsx';

import logo from '../../img/centreon.png';

import styles from './logo.scss';

class Logo extends Component {
  render() {
    const { customClass, onClick } = this.props;

    return (
      <div
        onClick={onClick}
        className={clsx(styles.logo, styles[customClass || ''])}
      >
        <span>
          <img
            className={clsx(styles['logo-image'])}
            src={logo}
            width="254"
            height="57"
            alt=""
          />
        </span>
      </div>
    );
  }
}

export default Logo;
