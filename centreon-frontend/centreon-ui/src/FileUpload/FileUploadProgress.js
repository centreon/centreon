import React, { Component } from 'react';
import classnames from 'classnames';
import styles from './file-upload.scss';

class FileUploadItem extends Component {
  render() {
    const { title, titleStatus, progressBar, uploadedPercentage } = this.props;
    const cnFileUploadTitle = classnames(
      styles['file-upload-item-title'],
      titleStatus || '',
    );
    return (
      <React.Fragment>
        <div className={classnames(styles['file-upload-item'])}>
          <span className={cnFileUploadTitle}>{title}</span>
          <div className={classnames(styles.progress)}>
            <span
              className={classnames(
                styles['progress-bar'],
                styles[progressBar],
              )}
            />
          </div>
          <span
            className={classnames(
              styles['file-upload-item-info'],
              styles.percentage,
            )}
          >
            {`${uploadedPercentage}%/100%`}
          </span>
        </div>
      </React.Fragment>
    );
  }
}

export default FileUploadItem;
