import React, { Component } from "react";
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
      onDeleteFile
    } = this.props;

    const cnFileUploadIcon = classnames(styles["file-upload-item-icon"], styles[icon ? icon : ''], styles[iconStatus ? iconStatus : ''] );
    const cnFileUploadTitle = classnames(styles["file-upload-item-title"], styles[titleStatus ? titleStatus : '']);
    const cnFileUploadInfo = classnames(styles["file-upload-item-info"], styles[infoStatus ? infoStatus : '']);
    return (
      <React.Fragment>
        <div className={classnames(styles["file-upload-item"])}>
          {icon ? (
            <span className={cnFileUploadIcon}/>
          ) : null}
          <span className={cnFileUploadTitle}>
            {title}
          </span>
          {info ? (
            <span className={cnFileUploadInfo}>
              {infoStatusLabel}
              {info}
            </span>
          ) : null}
          {!uploading ? (
            <span
              className={classnames("icon-close", "icon-close-small")}
              onClick={onDeleteFile}
            />
          ) : null}
          <div className={classnames("progress")}>
            <span 
            className={classnames(styles["progress-bar"], progressBar)}
            style={
              {
                width: `${progressPercentage}%`
              }
            } />
          </div>
          {
            message ? (
              <span className={classnames(styles["file-upload-message"])}>{message}</span>
            ) : null
          }      
        </div>
      </React.Fragment>
    );
  }
}

export default FileUploadItem;
