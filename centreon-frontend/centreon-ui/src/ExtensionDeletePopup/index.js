import React from "react";
import classnames from 'classnames';
import styles from '../Popup/popup.scss';
import Popup from "../Popup";
import Title from "../Title";
import MessageInfo from "../Message/MessageInfo";
import Button from "../Button/ButtonRegular";
import IconClose from "../Icon/IconClose";

class ExtensionDeletePopup extends React.Component {
  render() {
    const { deletingEntity, onConfirm, onCancel } = this.props;

    return (
      <Popup popupType="small">
        <div className={classnames(styles["popup-header"])}>
          <Title
            label={deletingEntity.description}
          />
        </div>
        <div className={classnames(styles["popup-body"])}>
          <MessageInfo
            messageInfo="red"
            text="Do you want to delete this extension? This action will remove all associated data."
          />
        </div>
        <div className={classnames(styles["popup-footer"])}>
          <div className={classnames(styles["container__row"])}>
            <div className={classnames(styles["container__col-xs-6"])}>
              <Button
                label="Delete"
                buttonType="regular"
                color="red"
                onClick={e => {
                  e.preventDefault();
                  e.stopPropagation();
                  onConfirm(deletingEntity.id, deletingEntity.type);
                }}
              />
            </div>
            <div className={classnames(styles["container__col-xs-6"], ["text-left"])}>
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
          iconPosition="icon-close-position-middle"
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
