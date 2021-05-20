/* eslint-disable jsx-a11y/click-events-have-key-events */
/* eslint-disable jsx-a11y/no-static-element-interactions */
/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */
/* eslint-disable react/prefer-stateless-function */

import React, { Component } from 'react';

import clsx from 'clsx';

import styles from './file-upload.scss';

class FileUploadItem extends Component {
  render() {
    const {
      icon,
      iconStatus,
      title,
      titleStatus,
      info,
      infoStatus,
      infoStatusLabel,
      progressBar,
      progressPercentage,
      uploading,
      message,
      onDeleteFile,
    } = this.props;
    const cnFileUploadIcon = clsx(
      styles['file-upload-item-icon'],
      styles[icon || ''],
      styles[iconStatus || ''],
    );
    const cnFileUploadTitle = clsx(
      styles['file-upload-item-title'],
      styles[titleStatus || ''],
    );
    const cnFileUploadInfo = clsx(
      styles['file-upload-item-info'],
      styles[infoStatus || ''],
    );
    return (
      <>
        <div className={clsx(styles['file-upload-item'])}>
          {icon ? <span className={cnFileUploadIcon} /> : null}
          <span className={cnFileUploadTitle}>{title}</span>
          {info ? (
            <span className={cnFileUploadInfo}>
              {infoStatusLabel}
              {info}
            </span>
          ) : null}
          {!uploading ? (
            <span
              className={clsx(styles['icon-close'], styles['icon-close-small'])}
              onClick={onDeleteFile}
            />
          ) : null}
          <div className={clsx(styles.progress)}>
            <span
              className={clsx(styles['progress-bar'], styles[progressBar])}
              style={{
                width: `${progressPercentage}%`,
              }}
            />
          </div>
          {message ? (
            <span className={clsx(styles['file-upload-message'])}>
              {message}
            </span>
          ) : null}
        </div>
      </>
    );
  }
}

export default FileUploadItem;
