import React, { Component } from "react";

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
    return (
      <React.Fragment>
        <div className="file-upload-item">
          {icon ? (
            <span className={`file-upload-item-icon ${icon} ${iconStatus}`} />
          ) : null}
          <span className={`file-upload-item-title ${titleStatus}`}>
            {title}
          </span>
          {info ? (
            <span className={`file-upload-item-info ${infoStatus}`}>
              {infoStatusLabel}
              {info}
            </span>
          ) : null}
          {!uploading ? (
            <span
              className="icon-close icon-close-small"
              onClick={onDeleteFile}
            />
          ) : null}
          <div className="progress">
            <span className={`progress-bar ${progressBar}`} style={
              {
                width: `${progressPercentage}%`
              }
            } />
          </div>
          {
            message ? (
              <span class="file-upload-message">{message}</span>
            ) : null
          }      
        </div>
      </React.Fragment>
    );
  }
}

export default FileUploadItem;
