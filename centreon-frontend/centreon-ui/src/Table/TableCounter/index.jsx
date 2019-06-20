/* eslint-disable react/prop-types */

import React from 'react';
import classnames from 'classnames';
import IconAction from '../../Icon/IconAction';
import styles from './table-counter.scss';

function TableCounter({ activeClass, number }) {
  return (
    <div
      className={classnames(styles['table-counter'], styles[activeClass || ''])}
    >
      <span className={classnames(styles['table-counter-number'])}>
        {number}
        <IconAction
          iconDirection="icon-position-counter"
          iconActionType="arrow-right"
        />
      </span>
      <div className={classnames(styles['table-counter-dropdown'])}>
        <span className={classnames(styles['table-counter-number'])}>
          {number}
        </span>
        <span
          className={classnames(styles['table-counter-number'], styles.active)}
        >
          {number}
        </span>
        <span className={classnames(styles['table-counter-number'])}>
          {number}
        </span>
        <span className={classnames(styles['table-counter-number'])}>
          {number}
        </span>
      </div>
    </div>
  );
}

export default TableCounter;
