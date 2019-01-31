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
      uploading,
      onDeleteFile
    } = this.props;
    return (
      <React.Fragment>
        <div className="file-upload-item">
          {icon ? <span className={`file-upload-item-icon ${icon} ${iconStatus}`} /> : null}
          <span className={`file-upload-item-title ${titleStatus}`}>{title}</span>
          {info ? <span className={`file-upload-item-info ${infoStatus}`}>
            {infoStatusLabel}
            {info}
          </span> : null}
          {
            !uploading ? (<span className="icon-close icon-close-small" onClick={onDeleteFile} />) : null
          }
          {
            progressBar ? (
              <div className="progress">
                <span className={`progress-bar ${progressBar}`} />
              </div>
            ) : null
          }

        </div>
      </React.Fragment>
    );
  }
}

export default FileUploadItem;
