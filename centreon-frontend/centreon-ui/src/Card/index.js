/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */
/* eslint-disable react/prefer-stateless-function */

import React, { Component } from 'react';

import clsx from 'clsx';

import styles from './card.scss';

class Card extends Component {
  render() {
    const { children, style } = this.props;
    return (
      <div style={style} className={clsx(styles.card)}>
        <div>{children}</div>
      </div>
    );
  }
}

export default Card;
