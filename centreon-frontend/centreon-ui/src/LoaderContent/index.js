/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */
/* eslint-disable react/prefer-stateless-function */

import React, { Component } from 'react';
import classnames from 'classnames';
import loaderStyles from 'loaders.css/loaders.min.css';
import styles from './loader-content.scss';

class Loader extends Component {
  render() {
    const { className } = this.props;
    const cn = classnames(
      styles.loader,
      styles.content,
      styles[className || ''],
    );
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
}

export default Loader;
