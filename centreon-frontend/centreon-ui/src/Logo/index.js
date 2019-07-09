/* eslint-disable react/jsx-filename-extension */
/* eslint-disable jsx-a11y/no-static-element-interactions */
/* eslint-disable jsx-a11y/click-events-have-key-events */
/* eslint-disable react/destructuring-assignment */
/* eslint-disable react/prop-types */
/* eslint-disable react/prefer-stateless-function */

import React, { Component } from 'react';
import classnames from 'classnames';
import styles from './logo.scss';
import logo from '../../img/centreon.png';

class Logo extends Component {
  render() {
    const { customClass, onClick } = this.props;
    console.log(this.props.customClass);
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
}

export default Logo;
