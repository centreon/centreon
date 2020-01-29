/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */

import React from 'react';
import clsx from 'clsx';
import styles from './content-horizontal-line.scss';

const HorizontalLineContent = ({ hrTitle, hrColor, hrTitleColor }) => (
  <div
    className={clsx(styles['content-hr'], {
      [styles[`content-hr-${hrColor}`]]: hrColor,
    })}
  >
    <span
      className={clsx(styles['content-hr-title'], {
        [styles[`content-hr-title-${hrTitleColor}`]]: hrTitleColor,
      })}
    >
      {hrTitle}
    </span>
  </div>
);

export default HorizontalLineContent;
