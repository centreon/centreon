/* eslint-disable react/prop-types */

import React from 'react';
import classnames from 'classnames';
import loaderStyles from 'loaders.css/loaders.min.css';
import styles from './loader-content.scss';

function Loader({ className }) {
  const cn = classnames(styles.loader, styles.content, styles[className || '']);
  return (
    <div className={cn}>
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
}

export default Loader;
