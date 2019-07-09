/* eslint-disable jsx-a11y/click-events-have-key-events */
/* eslint-disable jsx-a11y/no-static-element-interactions */
/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */

import React, { Component } from 'react';
import classnames from 'classnames';
import Files from 'react-files';
import styles from './file-upload.scss';
import Button from '../Button/ButtonRegular';
import Popup from '../Popup';
import FileUploadItem from './FileUploadItem';
import IconClose from '../Icon/IconClose';

class FileUpload extends Component {
  state = {
    files: [],
  };

  onFilesChange = (files) => {
    this.setState({
      files,
    });
  };

  onFilesError = (error) => {
    console.log(`error code ${error.code}: ${error.message}`);
  };

  onRemoveFile = (idx) => {
    const { files } = this.state;
    files.splice(idx, 1);
    this.setState({
      files,
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
    } = this.props;
    let isSuccessfull = true;
    if (uploadStatus && !uploadStatus.status) {
      isSuccessfull = false;
    }
    return (
      <>
        <Popup popupType="small">
          <div
            className={classnames(
              styles['popup-header'],
              styles[
                isSuccessfull
                  ? 'blue-background-decorator'
                  : 'red-background-decorator'
              ],
            )}
          >
            <div className={classnames(styles.container__row)}>
              <div
                className={classnames(
                  styles['container__col-xs-6'],
                  styles['center-vertical'],
                  styles['m-0'],
                )}
              >
                <div className={classnames(styles.file, styles['file-upload'])}>
                  <span className={classnames(styles['file-upload-title'])}>
                    <span
                      className={classnames(
                        styles['file-upload-icon'],
                        styles.white,
                      )}
                    />
                    {isSuccessfull ? 'File Upload' : 'No valid file uploaded.'}
                  </span>
                </div>
              </div>
              {!finished ? (
                <div
                  className={classnames(
                    styles['container__col-xs-6'],
                    styles['center-vertical'],
                    styles['m-0'],
                  )}
                >
                  <Files
                    className={classnames('test')}
                    onChange={this.onFilesChange}
                    onError={this.onFilesError}
                    accepts={['.zip', '.license']}
                    multiple
                    maxFiles={5}
                    maxFileSize={1048576}
                    minFileSize={0}
                    clickable
                  >
                    <div
                      className={classnames(
                        styles['container__col-xs-6'],
                        styles['text-right'],
                      )}
                    >
                      <Button
                        buttonType="bordered"
                        color="white"
                        label="BROWSE"
                      />
                    </div>
                  </Files>
                </div>
              ) : null}
            </div>
            <span
              className={classnames(
                styles['icon-close'],
                styles['icon-close-middle'],
              )}
              onClick={onClose}
            />
          </div>
          {files.length > 0 ? (
            <div className={classnames(styles['popup-body'])}>
              <div
                className={classnames(
                  styles.file,
                  styles['file-upload'],
                  styles['file-upload-body-container'],
                )}
              >
                <div className={classnames(styles['file-upload-items'])}>
                  {!uploadStatus ? (
                    files.map((file, idx) => (
                      <FileUploadItem
                        icon={file.extension === 'zip' ? 'zip' : 'file'}
                        iconStatus={uploading ? 'percentage' : 'warning'}
                        title={file.name}
                        titleStatus={uploading ? 'percentage' : 'warning'}
                        infoStatus={uploading ? 'percentage' : 'warning'}
                        progressBar={uploading ? 'percentage' : ''}
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
                  ) : (
                    <>
                      {uploadStatus.result.successed.map(({ license }) => (
                        <FileUploadItem
                          icon="file"
                          iconStatus="success"
                          title={license}
                          titleStatus="success"
                          infoStatus="success"
                          progressBar="success"
                          progressPercentage={100}
                          uploading
                        />
                      ))}
                      {uploadStatus.result.errors.map(
                        ({ license, message }) => (
                          <FileUploadItem
                            icon="file"
                            iconStatus="error"
                            title={license}
                            titleStatus="error"
                            infoStatus="error"
                            progressBar="error"
                            progressPercentage={100}
                            message={message}
                            uploading
                          />
                        ),
                      )}
                    </>
                  )}
                </div>
                {!finished ? (
                  <Button
                    label="Apply"
                    buttonType={uploading ? 'bordered' : 'regular'}
                    color={uploading ? 'gray' : 'blue'}
                    onClick={() => {
                      if (!uploading) {
                        onApply(files);
                      }
                    }}
                  />
                ) : (
                  <Button
                    label="Ok"
                    buttonType="regular"
                    color="green"
                    onClick={onClose}
                  />
                )}
              </div>
            </div>
          ) : null}
          <IconClose
            iconPosition="icon-close-position-small"
            iconType="middle"
            onClick={onClose}
          />
        </Popup>
      </>
    );
  }
}

export default FileUpload;
