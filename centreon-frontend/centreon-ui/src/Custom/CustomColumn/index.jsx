/* eslint-disable react/prop-types */

import React from 'react';
import classnames from 'classnames';
import styles from '../../global-sass-files/_grid.scss';

function CustomColumn({
  children,
  customColumn,
  additionalStyles,
  additionalColumns,
}) {
  const additionalClasses = [];
  if (additionalStyles) {
    for (let i = 0; i < additionalStyles.length; i += 1) {
      additionalClasses.push(styles[additionalStyles[i]]);
    }
  }

  return (
    <div
      className={classnames(
        { [styles[`container__col-${customColumn}`]]: true },
        additionalClasses,
        additionalColumns
          ? { [styles[`container__col-${additionalColumns}`]]: true }
          : '',
      )}
    >
      {children}
    </div>
  );
}

export default CustomColumn;
