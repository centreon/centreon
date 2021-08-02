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
                    clickable
                    multiple
                    accepts={['.zip', '.license']}
                    className={clsx('test')}
                    maxFileSize={1048576}
                    maxFiles={5}
                    minFileSize={0}
                    onChange={this.onFilesChange}
                    onError={this.onFilesError}
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
                        icon={file.extension === 'zip' ? 'zip' : 'file'}
                        iconStatus={uploading ? 'percentage' : 'warning'}
                        info={file.sizeReadable}
                        infoStatus={uploading ? 'percentage' : 'warning'}
                        key={file.name}
                        progressBar={uploading ? 'percentage' : ''}
                        progressPercentage={
                          uploadingProgress[idx] ? uploadingProgress[idx] : 0
                        }
                        title={file.name}
                        titleStatus={uploading ? 'percentage' : 'warning'}
                        uploading={uploading}
                        onDeleteFile={() => {
                          this.onRemoveFile(idx);
                        }}
                      />
                    ))
                  ) : (
                    <>
                      {uploadStatus.result.successed.map(({ license }) => (
                        <FileUploadItem
                          uploading
                          icon="file"
                          iconStatus="success"
                          infoStatus="success"
                          key={license}
                          progressBar="success"
                          progressPercentage={100}
                          title={license}
                          titleStatus="success"
                        />
                      ))}
                      {uploadStatus.result.errors.map(
                        ({ license, message }) => (
                          <FileUploadItem
                            uploading
                            icon="file"
                            iconStatus="error"
                            infoStatus="error"
                            key={license}
                            message={message}
                            progressBar="error"
                            progressPercentage={100}
                            title={license}
                            titleStatus="error"
                          />
                        ),
                      )}
                    </>
                  )}
                </div>
                {!finished ? (
                  <Button
                    buttonType={uploading ? 'bordered' : 'regular'}
                    color={uploading ? 'gray' : 'blue'}
                    label="Apply"
                    onClick={() => {
                      if (!uploading) {
                        onApply(files);
                      }
                    }}
                  />
                ) : (
                  <Button
                    buttonType="regular"
                    color="green"
                    label="Ok"
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
