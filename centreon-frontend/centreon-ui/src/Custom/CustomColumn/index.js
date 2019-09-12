/* eslint-disable react/jsx-filename-extension */
/* eslint-disable no-plusplus */
/* eslint-disable react/prop-types */
/* eslint-disable react/prefer-stateless-function */

import React, { Component } from 'react';
import classnames from 'classnames';
import styles from '../../global-sass-files/_grid.scss';

class CustomColumn extends Component {
  render() {
    const {
      children,
      customColumn,
      additionalStyles,
      additionalColumns,
      style,
    } = this.props;
    const additionalClasses = [];
    if (additionalStyles) {
      for (let i = 0; i < additionalStyles.length; i++) {
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
        style={style}
      >
        {children}
      </div>
    );
  }
}

export default CustomColumn;
