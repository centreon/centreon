/* eslint-disable jsx-a11y/click-events-have-key-events */
/* eslint-disable jsx-a11y/no-static-element-interactions */
/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */
/* eslint-disable react/prefer-stateless-function */

import React, { Component } from 'react';
import classnames from 'classnames';
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
    const cnFileUploadIcon = classnames(
      styles['file-upload-item-icon'],
      styles[icon || ''],
      styles[iconStatus || ''],
    );
    const cnFileUploadTitle = classnames(
      styles['file-upload-item-title'],
      styles[titleStatus || ''],
    );
    const cnFileUploadInfo = classnames(
      styles['file-upload-item-info'],
      styles[infoStatus || ''],
    );
    return (
      <React.Fragment>
        <div className={classnames(styles['file-upload-item'])}>
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
              className={classnames(
                styles['icon-close'],
                styles['icon-close-small'],
              )}
              onClick={onDeleteFile}
            />
          ) : null}
          <div className={classnames(styles.progress)}>
            <span
              className={classnames(
                styles['progress-bar'],
                styles[progressBar],
              )}
              style={{
                width: `${progressPercentage}%`,
              }}
            />
          </div>
          {message ? (
            <span className={classnames(styles['file-upload-message'])}>
              {message}
            </span>
          ) : null}
        </div>
      </React.Fragment>
    );
  }
}

export default FileUploadItem;
