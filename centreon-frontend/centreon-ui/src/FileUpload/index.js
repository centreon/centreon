/* eslint-disable jsx-a11y/click-events-have-key-events */
/* eslint-disable jsx-a11y/no-static-element-interactions */
/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */

import React, { Component } from 'react';

import clsx from 'clsx';
import Files from 'react-files';

import Button from '../Button';
import Popup from '../Popup';
import IconClose from '../Icon/IconClose';

import FileUploadItem from './FileUploadItem';
import styles from './file-upload.scss';

class FileUpload extends Component {
  state = {
    files: [],
  };

  onFilesChange = (files) => {
    this.setState({
      files,
    });
  };

  onFilesError = () => {};

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
            className={clsx(
              styles['popup-header'],
              styles[
                isSuccessfull
                  ? 'blue-background-decorator'
                  : 'red-background-decorator'
              ],
            )}
          >
            <div className={clsx(styles.container__row)}>
              <div
                className={clsx(
                  styles['container__col-xs-6'],
                  styles['center-vertical'],
                  styles['m-0'],
                )}
              >
                <div className={clsx(styles.file, styles['file-upload'])}>
                  <span className={clsx(styles['file-upload-title'])}>
                    <span
                      className={clsx(styles['file-upload-icon'], styles.white)}
                    />
                    {isSuccessfull ? 'File Upload' : 'No valid file uploaded.'}
                  </span>
                </div>
              </div>
              {!finished ? (
                <div
                  className={clsx(
                    styles['container__col-xs-6'],
                    styles['center-vertical'],
                    styles['m-0'],
                  )}
                >
                  <Files
                    className={clsx('test')}
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
                      className={clsx(
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
              className={clsx(
                styles['icon-close'],
                styles['icon-close-middle'],
              )}
              onClick={onClose}
            />
          </div>
          {files.length > 0 ? (
            <div className={clsx(styles['popup-body'])}>
              <div
                className={clsx(
                  styles.file,
                  styles['file-upload'],
                  styles['file-upload-body-container'],
                )}
              >
                <div className={clsx(styles['file-upload-items'])}>
                  {!uploadStatus ? (
                    files.map((file, idx) => (
                      <FileUploadItem
                        key={file.name}
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
                          key={license}
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
                            key={license}
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
