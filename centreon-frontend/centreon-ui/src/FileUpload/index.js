import React, { Component } from "react";
import Button from "../Button/ButtonRegular";
import Popup from "../Popup";
import FileUploadItem from "./FileUploadItem";
import "./file-upload.scss";
import Files from "react-files";

class FileUpload extends Component {
  state = {
    files: []
  };

  onFilesChange = files => {
    this.setState({
      files
    });
  };

  onFilesError = error => {
    console.log("error code " + error.code + ": " + error.message);
  };

  onRemoveFile = idx => {
    let { files } = this.state;
    files.splice(idx, 1);
    this.setState({
      files
    });
  };

  render() {
    const { files } = this.state;
    const {
      uploadingProgress,
      uploadStatus,
      onClose,
      uploading,
      onApply,
      finished,
      iconColor
    } = this.props;
    let isSuccessfull = true;
    if (uploadStatus) {
      if (!uploadStatus.status) {
        isSuccessfull = false;
      }
    }
    return (
      <React.Fragment>
        <Popup popupType="small">
          <div
            className={`popup-header ${
              isSuccessfull ? "blue" : "red"
            }-background-decorator`}
          >
            <div className="container__row">
              <div className="container__col-xs-6 center-vertical">
                <div className="file file-upload">
                  <span className="file-upload-title">
                    <span className={`file-upload-icon white`} />
                    {isSuccessfull ? "File Upload" : "No valid file uploaded."}
                  </span>
                </div>
              </div>
              <div className="container__col-xs-6 center-vertical">
                <Files
                  className="test"
                  onChange={this.onFilesChange}
                  onError={this.onFilesError}
                  accepts={['.zip', '.license']}
                  multiple
                  maxFiles={5}
                  maxFileSize={1048576}
                  minFileSize={0}
                  clickable
                >
                <div className="container__col-xs-6 text-right">
                  <Button buttonType="bordered" color="white" label="BROWSE" />
                </div>
              </Files>
              </div>
            </div>
            <span className="icon-close icon-close-middle" onClick={onClose} />
          </div>
          {files.length > 0 && isSuccessfull ? (
            <div className="popup-body">
              <div className="file file-upload file-upload-body-container">
                <div className="file-upload-items">
                  {!uploadStatus ? (
                    files.map((file, idx) => (
                      <FileUploadItem
                        icon={file.extension === "zip" ? "zip" : "file"}
                        iconStatus={uploading ? "percentage" : "warning"}
                        title={file.name}
                        titleStatus={uploading ? "percentage" : "warning"}
                        infoStatus={uploading ? "percentage" : "warning"}
                        progressBar={uploading ? "percentage" : ""}
                        progressPercentage={
                          uploadingProgress[idx] ? uploadingProgress[idx] : 0
                        }
                        info={file.sizeReadable}
                        onDeleteFile={() => {
                          this.onRemoveFile(idx);
                        }}
                        uploading={uploading}
                      />
                    ))
                  ) : isSuccessfull ? (
                    <React.Fragment>
                      {uploadStatus.result.successed.map(({ license }) => (
                        <FileUploadItem
                          icon={"file"}
                          iconStatus={"success"}
                          title={license}
                          titleStatus={"success"}
                          infoStatus={"success"}
                          progressBar={"success"}
                          progressPercentage={100}
                          uploading={true}
                        />
                      ))}
                      {uploadStatus.result.errors.map(
                        ({ license, message }) => (
                          <FileUploadItem
                            icon={"file"}
                            iconStatus={"error"}
                            title={license}
                            titleStatus={"error"}
                            infoStatus={"error"}
                            progressBar={"error"}
                            progressPercentage={100}
                            message={message}
                            uploading={true}
                          />
                        )
                      )}
                    </React.Fragment>
                  ) : null}
                </div>
                {!finished ? (
                  <Button
                    label={"Apply"}
                    buttonType={uploading ? "bordered" : "regular"}
                    color={uploading ? "gray" : "blue"}
                    onClick={() => {
                      if (!uploading) {
                        onApply(files);
                      }
                    }}
                  />
                ) : (
                  <Button
                    label={"Ok"}
                    buttonType="regular"
                    color="green"
                    onClick={onClose}
                  />
                )}
              </div>
            </div>
          ) : null}
        </Popup>
      </React.Fragment>
    );
  }
}

export default FileUpload;
