/* eslint-disable jsx-a11y/no-static-element-interactions */
/* eslint-disable jsx-a11y/click-events-have-key-events */
/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */

import React from 'react';
import clsx from 'clsx';
import styles from './icon-header.scss';

const IconHeader = ({ iconType, iconName, style, onClick, children }) => {
  return (
    <span className={clsx(styles['icons-wrap'])} style={style}>
      <span
        onClick={onClick}
        className={clsx(styles.iconmoon, {
          [styles[`icon-${iconType}`]]: true,
        })}
      />
      <span className={clsx(styles.icons__name)}>{iconName}</span>
      {children}
    </span>
  );
};

export default IconHeader;
