/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */

import React from 'react';

import clsx from 'clsx';

import { Typography } from '@material-ui/core';

import styles from './icon-header.scss';

const IconHeader = ({ Icon, iconName, style, onClick, children }) => {
  return (
    <span className={clsx(styles['icons-wrap'])} style={style}>
      <Icon style={{ color: '#FFFFFF', cursor: 'pointer' }} onClick={onClick} />
      <span className={clsx(styles.icons__name)}>
        <Typography variant="caption">{iconName}</Typography>
      </span>
      {children}
    </span>
  );
};

export default IconHeader;
