/* eslint-disable react/prop-types */

import React from 'react';
import classnames from 'classnames';
import styles from './card.scss';

function CardItem({
  children,
  itemBorderColor,
  itemFooterColor,
  itemFooterLabel,
  customClass,
  style,
}) {
  const cnCardItem = classnames(
    styles['card-item'],
    {
      [styles[`card-item-bordered-${itemBorderColor || ''}`]]: true,
    },
    styles[customClass || ''],
  );
  const cnCardItemFooter = classnames(styles['card-item-footer'], {
    [styles[`card-item-footer-${itemFooterColor || ''}`]]: true,
  });

  return (
    <div className={cnCardItem} style={style}>
      {children}
      <span className={cnCardItemFooter}>{itemFooterLabel}</span>
    </div>
  );
}

export default CardItem;
