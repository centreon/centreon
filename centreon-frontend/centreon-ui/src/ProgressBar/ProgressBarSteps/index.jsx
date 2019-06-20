/* eslint-disable react/prop-types */

import React from 'react';
import classnames from 'classnames';
import styles from './progress-bar-steps.scss';

function ProgressBarSteps({ children, customStyles }) {
  return (
    <div
      className={classnames(
        styles['progress-bar'],
        customStyles ? styles['progress-bar-custom-styles'] : '',
      )}
    >
      <div className={classnames(styles['progress-bar-wrapper'])}>
        <ul className={classnames(styles['progress-bar-items'])}>{children}</ul>
      </div>
    </div>
  );
}

export default ProgressBarSteps;
