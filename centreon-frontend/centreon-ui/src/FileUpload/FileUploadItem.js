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
      progressBar
    } = this.props;
    return (
      <React.Fragment>
        <div className="file-upload-item">
          <span className={`file-upload-item-icon ${icon} ${iconStatus}`} />
          <span className={`file-upload-item-title ${titleStatus}`}>{title}</span>
          <span className={`file-upload-item-info ${infoStatus}`}>
            {infoStatusLabel}
            {info}
          </span>
          <span className="icon-close icon-close-small" />
          <span className={`file-upload-item-progress ${progressBar}`} />
        </div>
      </React.Fragment>
    );
  }
}

export default FileUploadItem;
