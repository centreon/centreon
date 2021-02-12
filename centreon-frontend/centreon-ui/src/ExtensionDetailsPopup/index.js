/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */
/* eslint-disable react/prefer-stateless-function */

import React from 'react';

import clsx from 'clsx';

import styles from '../Popup/popup.scss';
import Popup from '../Popup';
import Slider from '../Slider/SliderContent';
import IconContent from '../Icon/IconContent';
import Title from '../Title';
import Button from '../Button';
import HorizontalLine from '../HorizontalLines';
import Description from '../Description';
import IconClose from '../Icon/IconClose';

import {
  SliderSkeleton,
  HeaderSkeleton,
  ContentSkeleton,
  ReleaseNoteSkeleton,
} from './LoadingSkeleton';

class ExtensionDetailPopup extends React.Component {
  render() {
    const {
      type,
      modalDetails,
      onCloseClicked,
      onDeleteClicked,
      onUpdateClicked,
      onInstallClicked,
      loading,
      animate,
    } = this.props;

    if (modalDetails === null) {
      return null;
    }

    return (
      <Popup popupType="big">
        {loading ? (
          <SliderSkeleton animate={animate} />
        ) : (
          <Slider
            type={type}
            images={!loading && modalDetails.images ? modalDetails.images : []}
          >
            {modalDetails.version.installed && modalDetails.version.outdated ? (
              <IconContent
                customClass="content-icon-popup-wrapper"
                iconContentType="update"
                iconContentColor="orange"
                onClick={() => {
                  onUpdateClicked(modalDetails.id, modalDetails.type);
                }}
              />
            ) : null}
            {modalDetails.version.installed ? (
              <IconContent
                customClass="content-icon-popup-wrapper"
                iconContentType="delete"
                iconContentColor="red"
                onClick={() => {
                  onDeleteClicked(modalDetails.id, modalDetails.type);
                }}
              />
            ) : (
              <IconContent
                customClass="content-icon-popup-wrapper"
                iconContentType="add"
                iconContentColor="green"
                onClick={() => {
                  onInstallClicked(modalDetails.id, modalDetails.type);
                }}
              />
            )}
          </Slider>
        )}
        <div className={clsx(styles['popup-header'])}>
          {loading ? (
            <HeaderSkeleton animate={animate} />
          ) : (
            <>
              <Title label={modalDetails.title} />
              <Button
                style={{ cursor: 'default' }}
                label={
                  (!modalDetails.version.installed ? 'Available ' : '') +
                  modalDetails.version.available
                }
                buttonType="regular"
                color="blue"
              />
              <Button
                label={modalDetails.stability}
                buttonType="bordered"
                color="gray"
                style={{ margin: '15px', cursor: 'default' }}
              />
            </>
          )}
        </div>
        <HorizontalLine />
        <div className={clsx(styles['popup-body'])}>
          {loading ? (
            <ContentSkeleton animate={animate} />
          ) : (
            <>
              {modalDetails.last_update ? (
                <Description date={`Last update ${modalDetails.last_update}`} />
              ) : null}
              <Description title="Description:" />
              <Description text={modalDetails.description} />
            </>
          )}
        </div>
        <HorizontalLine />
        <div className={clsx(styles['popup-footer'])}>
          {loading ? (
            <ReleaseNoteSkeleton animate={animate} />
          ) : (
            <Description note={modalDetails.release_note} link />
          )}
        </div>
        <IconClose
          iconPosition="icon-close-position-big"
          iconType="big"
          onClick={onCloseClicked}
        />
      </Popup>
    );
  }
}

export default ExtensionDetailPopup;
