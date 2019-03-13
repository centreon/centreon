import React from "react";
import Popup from "../Popup";
import Title from "../Title";
import MessageInfo from "../Message/MessageInfo";
import Button from "../Button";
import IconClose from "../Icon/IconClose";

class ExtensionDeletePopup extends React.Component {
  render() {
    const { deletingEntity, onConfirm, onCancel } = this.props;

    return (
      <Popup popupType="small">
        <div class="popup-header">
          <Title
            label={deletingEntity.description}
            icon={deletingEntity.type === "module" ? "object" : "puzzle"}
          />
        </div>
        <div class="popup-body">
          <MessageInfo
            messageInfo="red"
            text="Do you want to delete this extension. This, action will remove all associated data."
          />
        </div>
        <div className="popup-footer">
          <div class="container__row">
            <div class="container__col-xs-6">
              <Button
                label="Delete"
                buttonType="regular"
                color="red"
                iconActionType="delete-white"
                onClick={e => {
                  e.preventDefault();
                  e.stopPropagation();
                  onConfirm(deletingEntity.id, deletingEntity.type);
                }}
              />
            </div>
            <div class="container__col-xs-6 text-right">
              <Button
                label="Cancel"
                buttonType="regular"
                color="gray"
                onClick={e => {
                  e.preventDefault();
                  e.stopPropagation();
                  onCancel();
                }}
              />
            </div>
          </div>
        </div>
        <IconClose
          iconType="middle"
          onClick={e => {
            e.preventDefault();
            e.stopPropagation();
            onCancel();
          }}
        />
      </Popup>
    );
  }
}

export default ExtensionDeletePopup;
