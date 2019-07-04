import React from 'react';
import classnames from 'classnames';
import styles from '../Popup/popup.scss';
import Popup from '../Popup';
import Loader from '../Loader';
import Slider from '../Slider/SliderContent';
import IconContent from '../Icon/IconContent';
import Title from '../Title';
import Subtitle from '../Subtitle';
import Button from '../Button/ButtonRegular';
import HorizontalLine from '../HorizontalLines/HorizontalLineRegular';
import Description from '../Description';
import IconClose from '../Icon/IconClose';

class ExtensionDetailPopup extends React.Component {
  render() {
    const {
      type,
      onCloseClicked,
      modalDetails,
      onVersionClicked,
      onDeleteClicked,
      onUpdateClicked,
      onInstallClicked,
      loading,
    } = this.props;

    if (modalDetails === null) {
      return null;
    }
    return (
      <Popup popupType="big">
        {loading ? <Loader fullContent /> : null}
        <Slider type={type} images={modalDetails.images || []}>
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
        <div className={classnames(styles['popup-header'])}>
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
        </div>
        <HorizontalLine />
        <div className={classnames(styles['popup-body'])}>
          {modalDetails.last_update ? (
            <Description date={`Last update ${modalDetails.last_update}`} />
          ) : null}
          <Description title="Description:" />
          <Description text={modalDetails.description} />
        </div>
        <HorizontalLine />
        <div className={classnames(styles['popup-footer'])}>
          <Description note={modalDetails.release_note} link />
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
