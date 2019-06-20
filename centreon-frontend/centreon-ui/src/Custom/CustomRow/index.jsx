/* eslint-disable react/prop-types */

import React from 'react';
import classnames from 'classnames';
import styles from '../../global-sass-files/_grid.scss';

function CustomRow({ children, additionalStyles }) {
  const additionalClasses = [];
  if (additionalStyles) {
    for (let i = 0; i < additionalStyles.length; i += 1) {
      additionalClasses.push(styles[additionalStyles[i]]);
    }
  }

  return (
    <div className={classnames(styles.container__row, additionalClasses)}>
      {children}
    </div>
  );
}

export default CustomRow;
