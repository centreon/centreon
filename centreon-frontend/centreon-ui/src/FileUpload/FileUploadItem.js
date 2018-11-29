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
        <div class="file-upload-item">
          <span class={`file-upload-item-icon ${icon} ${iconStatus}`} />
          <span class={`file-upload-item-title ${titleStatus}`}>{title}</span>
          <span class={`file-upload-item-info ${infoStatus}`}>
            {infoStatusLabel}
            {info}
          </span>
          <span class="icon-close icon-close-small" />
          <span class={`file-upload-item-progress ${progressBar}`} />
        </div>
      </React.Fragment>
    );
  }
}

export default FileUploadItem;
