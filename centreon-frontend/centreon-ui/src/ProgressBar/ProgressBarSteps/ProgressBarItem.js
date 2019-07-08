/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */

import React from 'react';
import classnames from 'classnames';
import styles from './progress-bar-steps.scss';

const ProgressBarItem = ({ classActive, number }) => {
  return (
    <li className={classnames(styles['progress-bar-item'])}>
      <span
        className={classnames(
          styles['progress-bar-link'],
          styles[classActive || ''],
        )}
      >
        {number}
      </span>
    </li>
  );
};

export default ProgressBarItem;
