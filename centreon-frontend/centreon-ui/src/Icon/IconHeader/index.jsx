/* eslint-disable react/prop-types */
/* eslint-disable jsx-a11y/no-static-element-interactions */
/* eslint-disable jsx-a11y/click-events-have-key-events */

import React from 'react';
import classnames from 'classnames';
import styles from './icon-header.scss';

const IconHeader = ({ iconType, iconName, style, onClick, children }) => {
  return (
    <span className={classnames(styles['icons-wrap'])} style={style}>
      <span
        onClick={onClick}
        className={classnames(styles.iconmoon, {
          [styles[`icon-${iconType}`]]: true,
        })}
      />
      <span className={classnames(styles.icons__name)}>{iconName}</span>
      {children}
    </span>
  );
};

export default IconHeader;
