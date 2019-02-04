import React, { Component } from "react";

class FileUploadItem extends Component {
  render() {
    const { title, titleStatus, progressBar, uploadedPercentage } = this.props;
    return (
      <React.Fragment>
        <div className="file-upload-item">
          <span className={`file-upload-item-title ${titleStatus}`}>
            {title}
          </span>
          <div className="progress">
            <span className={`progress-bar ${progressBar}`} />
          </div>
          <span
            className={`file-upload-item-info percentage`}
          >{`${uploadedPercentage}%/100%`}</span>
        </div>
      </React.Fragment>
    );
  }
}

export default FileUploadItem;
