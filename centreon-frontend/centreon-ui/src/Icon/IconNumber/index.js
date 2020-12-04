/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */

import React from 'react';

import clsx from 'clsx';

import styles from './icon-number.scss';

const IconNumber = ({ iconColor, iconType, iconNumber }) => {
  return (
    <span
      className={clsx(
        styles.icons,
        styles['icons-number'],
        styles[iconType],
        styles[iconColor],
        styles['number-wrap'],
      )}
    >
      <span className={clsx(styles['number-count'])}>{iconNumber}</span>
    </span>
  );
};

export default IconNumber;
