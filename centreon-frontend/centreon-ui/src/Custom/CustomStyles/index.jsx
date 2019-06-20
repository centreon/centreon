/* eslint-disable react/prop-types */

import React from 'react';
import classnames from 'classnames';
import styles from '../../global-sass-files/_grid.scss';

function CustomStyles({ children, customStyles, additionalStyles }) {
  const additionalClasses = [];
  if (additionalStyles) {
    for (let i = 0; i < additionalStyles.length; i += 1) {
      additionalClasses.push(styles[additionalStyles[i]]);
    }
  }

  return (
    <div
      className={classnames(
        customStyles ? { [styles[`${customStyles}`]]: true } : '',
        additionalClasses,
      )}
    >
      {children}
    </div>
  );
}

export default CustomStyles;
