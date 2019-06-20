/* eslint-disable react/prop-types */

import React from 'react';
import classnames from 'classnames';
import styles from './card.scss';

function Card({ children, style }) {
  return (
    <div style={style} className={classnames(styles.card)}>
      <div>{children}</div>
    </div>
  );
}

export default Card;
