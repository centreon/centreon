import React from "react";
import Button from "../Button/ButtonRegular";
import Popup from "../Popup";
import FileUploadItem from "./FileUploadItem";
import FileUploadProgress from './FileUploadProgress';
import "./file-upload.scss";

const FileUpload = () => {
  return (
    <React.Fragment>
      <Popup popupType="small">
        <div className="popup-header blue-background-decorator">
          <div className="container__row">
            <div className="container__col-xs-6 center-vertical">
              <div className="file file-upload">
                <span className="file-upload-title">
                  <span className="file-upload-icon"/>File Upload
                </span>
              </div>
            </div>
            <div className="container__col-xs-6 text-right">
              <Button buttonType="bordered" color="white" label="BROWSE"/>
            </div>
          </div>
          <span className="icon-close icon-close-middle"/>
        </div>
        <div className="popup-body">
          <div className="file file-upload">
            <div className="file-upload-items">
              <FileUploadItem
                icon="file"
                iconStatus="success"
                title="file-1.licence"
                titleStatus="success"
                info="0.3mb"
                progressBar="success"/>
              <FileUploadItem
                icon="file"
                iconStatus="success"
                title="file-1.licence"
                titleStatus="success"
                info="0.3mb"
                progressBar="success"/>
              <FileUploadItem
                icon="zip"
                iconStatus="success"
                title="file-1.licence"
                titleStatus="success"
                info="0.3mb"
                progressBar="success"/>
              <FileUploadItem
                icon="file"
                iconStatus="error"
                titleStatus="error"
                title="file-1.licence"
                infoStatus="error"
                infoStatusLabel="upload failed"
                progressBar="error"/>
              <FileUploadItem
                icon="file"
                iconStatus="warning"
                title="file-1.licence"
                titleStatus="warning"
                progressBar="warning"/>
              <FileUploadProgress
                title="Progress"
                titleStatus="percentage"
                progressBar='percentage'
                uploadedPercentage='70'/>
            </div>
          </div>
        </div>
      </Popup>
    </React.Fragment>
  );
};

export default FileUpload;
