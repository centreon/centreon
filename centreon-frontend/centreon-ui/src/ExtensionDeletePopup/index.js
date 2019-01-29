import React from "react";
import * as Centreon from '../index';

class ExtensionDeletePopup extends React.Component {


    render() {
        const { deletingEntity, onConfirm, onCancel } = this.props;

        return (
            <Centreon.Popup popupType="small">
                <div class="popup-header">
                    <Centreon.Title label={deletingEntity.description} icon="object" />
                </div>
                <div class="popup-body">
                    <Centreon.MessageInfo messageInfo="red" text="Do you want to delete this extension. This, action will remove all associated data." />
                </div>
                <div className="popup-footer">
                    <div class="container__row">
                        <div class="container__col-xs-6">
                            <Centreon.Button
                                label="Delete"
                                buttonType="regular"
                                color="red"
                                iconActionType="delete-white"
                                onClick={
                                    (e) => {
                                        e.preventDefault();
                                        e.stopPropagation();
                                        onConfirm(deletingEntity.id);
                                    }
                                }
                            />
                        </div>
                        <div class="container__col-xs-6 text-right">
                            <Centreon.Button
                                label="Cancel"
                                buttonType="regular"
                                color="gray"
                                onClick={
                                    (e) => {
                                        e.preventDefault();
                                        e.stopPropagation();
                                        onCancel();
                                    }
                                }
                            />
                        </div>
                    </div>
                </div>
                <Centreon.IconClose
                    iconType="middle"
                    onClick={
                        (e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            onCancel();
                        }
                    } />
            </Centreon.Popup>
        )
    }
}

export default ExtensionDeletePopup;