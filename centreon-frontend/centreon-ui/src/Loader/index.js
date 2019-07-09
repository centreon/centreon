/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */

import React from 'react';
import classnames from 'classnames';
import loaderStyles from 'loaders.css/loaders.min.css';
import styles from './loader-additions.scss';

export default ({ fullContent }) => (
  <div
    className={classnames(
      styles.loader,
      styles.content,
      styles[fullContent ? 'full-relative-content' : ''],
    )}
  >
    <div
      className={classnames(
        styles['loader-inner'],
        loaderStyles['ball-grid-pulse'],
      )}
    >
      <div />
      <div />
      <div />
      <div />
    </div>
  </div>
);
