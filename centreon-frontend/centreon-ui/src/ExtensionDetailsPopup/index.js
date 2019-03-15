import React from "react";
import Popup from "../Popup";
import Loader from "../Loader";
import Slider from "../Slider/SliderContent";
import IconContent from "../Icon/IconContent";
import Title from "../Title";
import Subtitle from "../Subtitle";
import Button from "../Button";
import HorizontalLine from "../HorizontalLines/HorizontalLineRegular";
import Description from "../Description";
import IconClose from "../Icon/IconClose";

class ExtensionDetailPopup extends React.Component {
  render() {
    const {
      onCloseClicked,
      modalDetails,
      onVersionClicked,
      onDeleteClicked,
      onUpdateClicked,
      onInstallClicked,
      loading
    } = this.props;
    if (modalDetails === null) {
      return null;
    }
    return (
      <Popup popupType="big">
        {loading ? <Loader fullContent={true} /> : null}
        <Slider images={modalDetails.images || []}>
          {modalDetails.version.installed && modalDetails.version.outdated ? (
            <IconContent
              iconContentType="update"
              iconContentColor="orange white"
              onClick={() => {
                onUpdateClicked(modalDetails.id, modalDetails.type);
              }}
            />
          ) : null}
          {modalDetails.version.installed ? (
            <IconContent
              iconContentType="delete"
              iconContentColor="red white"
              onClick={() => {
                onDeleteClicked(modalDetails.id, modalDetails.type);
              }}
            />
          ) : (
            <IconContent
              iconContentType="add"
              iconContentColor="green white"
              onClick={() => {
                onInstallClicked(modalDetails.id, modalDetails.type);
              }}
            />
          )}
        </Slider>
        <div class="popup-header">
          <Title label={modalDetails.title} />
          <Subtitle label={modalDetails.label} />
          <Button
            onClick={() => {
              onVersionClicked(modalDetails.id);
            }}
            label={`Available ${modalDetails.version.available}`}
            buttonType="regular"
            color="blue"
          />
          <Button
            label={modalDetails.stability}
            buttonType="bordered"
            color="gray"
            style={{ margin: "15px" }}
          />
          {modalDetails.license ? (
            <Button
              label={modalDetails.license}
              buttonType="bordered"
              color="orange"
            />
          ) : null}
        </div>
        <HorizontalLine />
        <div class="popup-body">
          {modalDetails.last_update ? (
            <Description
              date={`Last update ${modalDetails.last_update}`}
            />
          ) : null}
          <Description title="Description:" />
          <Description text={modalDetails.description} />
        </div>
        <HorizontalLine />
        <div className="popup-footer">
          <Description note={modalDetails.release_note} />
        </div>
        <IconClose iconType="big" onClick={onCloseClicked} />
      </Popup>
    );
  }
}

export default ExtensionDetailPopup;
