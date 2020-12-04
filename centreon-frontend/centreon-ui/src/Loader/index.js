/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */

import React from 'react';

import clsx from 'clsx';
import loaderStyles from 'loaders.css/loaders.min.css';

import styles from './loader-additions.scss';

export default ({ fullContent }) => (
  <div
    className={clsx(
      styles.loader,
      styles.content,
      styles[fullContent ? 'full-relative-content' : ''],
    )}
  >
    <div
      className={clsx(styles['loader-inner'], loaderStyles['ball-grid-pulse'])}
    >
      <div />
      <div />
      <div />
      <div />
    </div>
  </div>
);
