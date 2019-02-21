import React from "react";
import * as Centreon from "../index";

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
      <Centreon.Popup popupType="big">
        {loading ? <Centreon.Loader fullContent={true} /> : null}
        <Centreon.Slider images={modalDetails.images || []}>
          {modalDetails.version.installed && modalDetails.version.outdated ? (
            <Centreon.IconContent
              iconContentType="update"
              iconContentColor="orange white"
              onClick={() => {
                onUpdateClicked(modalDetails.id, modalDetails.type);
              }}
            />
          ) : null}
          {modalDetails.version.installed ? (
            <Centreon.IconContent
              iconContentType="delete"
              iconContentColor="red white"
              onClick={() => {
                onDeleteClicked(modalDetails.id, modalDetails.type);
              }}
            />
          ) : (
            <Centreon.IconContent
              iconContentType="add"
              iconContentColor="green white"
              onClick={() => {
                onInstallClicked(modalDetails.id, modalDetails.type);
              }}
            />
          )}
        </Centreon.Slider>
        <div class="popup-header">
          <Centreon.Title label={modalDetails.title} />
          <Centreon.Subtitle label={modalDetails.label} />
          <Centreon.Button
            onClick={() => {
              onVersionClicked(modalDetails.id);
            }}
            label={`Available ${modalDetails.version.available}`}
            buttonType="regular"
            color="blue"
          />
          <Centreon.Button
            label={modalDetails.stability}
            buttonType="bordered"
            color="gray"
            style={{ margin: "15px" }}
          />
          <Centreon.Button
            label={modalDetails.license}
            buttonType="bordered"
            color="orange"
          />
        </div>
        <Centreon.HorizontalLine />
        <div class="popup-body">
          <Centreon.Description
            date={`Last update ${modalDetails.last_update}`}
          />
          <Centreon.Description title="Description:" />
          <Centreon.Description text={modalDetails.description} />
        </div>
        <Centreon.HorizontalLine />
        <div className="popup-footer">
          <Centreon.Description note={modalDetails.release_note} />
        </div>
        <Centreon.IconClose iconType="big" onClick={onCloseClicked} />
      </Centreon.Popup>
    );
  }
}

export default ExtensionDetailPopup;
