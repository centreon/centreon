/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */
/* eslint-disable react/prefer-stateless-function */

import React, { Component } from 'react';

import clsx from 'clsx';

import styles from './file-upload.scss';

class FileUploadItem extends Component {
  render() {
    const { title, titleStatus, progressBar, uploadedPercentage } = this.props;
    const cnFileUploadTitle = clsx(
      styles['file-upload-item-title'],
      titleStatus || '',
    );
    return (
      <>
        <div className={clsx(styles['file-upload-item'])}>
          <span className={cnFileUploadTitle}>{title}</span>
          <div className={clsx(styles.progress)}>
            <span
              className={clsx(styles['progress-bar'], styles[progressBar])}
            />
          </div>
          <span
            className={clsx(styles['file-upload-item-info'], styles.percentage)}
          >
            {`${uploadedPercentage}%/100%`}
          </span>
        </div>
      </>
    );
  }
}

export default FileUploadItem;
