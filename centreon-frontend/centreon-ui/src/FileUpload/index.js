import React from "react";
import Button from "../Button/ButtonRegular";
import Popup from "../Popup";
import FileUploadItem from "./FileUploadItem";
import "./file-upload.scss";

const FileUpload = () => {
  return (
    <React.Fragment>
      <Popup popupType="small">
        <div class="popup-header blue-background-decorater">
          <div class="container__row">
            <div class="container__col-xs-6 center-vertical">
              <div class="file file-upload">
                <span class="file-upload-title">
                  <span class="file-upload-icon" />File Upload
                </span>
              </div>
            </div>
            <div class="container__col-xs-6 text-right">
              <Button buttonType="bordered" color="white" label="BROWSE" />
            </div>
          </div>
          <span class="icon-close icon-close-middle" />
        </div>
        <div class="popup-body">
          <div class="file file-upload">
            <div class="file-upload-items">
              <FileUploadItem
                icon="file"
                iconStatus="success"
                title="file-1.licence"
                titleStatus="success"
                info="0.3mb"
                progressBar="success"
              />
              <FileUploadItem
                icon="file"
                iconStatus="success"
                title="file-1.licence"
                titleStatus="success"
                info="0.3mb"
                progressBar="success"
              />
              <FileUploadItem
                icon="zip"
                iconStatus="success"
                title="file-1.licence"
                titleStatus="success"
                info="0.3mb"
                progressBar="success"
              />
              <FileUploadItem
                icon="file"
                iconStatus="error"
                titleStatus="error"
                title="file-1.licence"
                infoStatus="error"
                infoStatusLabel="upload failed"
                progressBar="error"
              />
              <FileUploadItem
                icon="file"
                iconStatus="warning"
                title="file-1.licence"
                titleStatus="warning"
                progressBar="warning"
              />
            </div>
          </div>
        </div>
      </Popup>
    </React.Fragment>
  );
};

export default FileUpload;
