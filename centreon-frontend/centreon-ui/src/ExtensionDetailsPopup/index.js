import React from "react";
import * as Centreon from '../index';

class ExtensionDetailPopup extends React.Component {


    render() {
        const {onCloseClicked, modalDetails, onVersionClicked} = this.props;
        if(modalDetails === null){
            return null;
        }
        return (
            <Centreon.Popup popupType="big">
                <Centreon.Slider />
                <div class="popup-header">
                    <Centreon.Title label={modalDetails.title} />
                    <Centreon.Subtitle label={modalDetails.label} />
                    <Centreon.Button
                        onClick={()=>{onVersionClicked(modalDetails.id)}}
                        label={`Available ${modalDetails.version.available}`}
                        buttonType="regular"
                        color="blue"
                    />
                    <Centreon.Button
                        label={modalDetails.stability}
                        buttonType="bordered"
                        color="gray"
                        style={{ margin: '15px' }}
                    />
                    <Centreon.Button
                        label={modalDetails.license}
                        buttonType="bordered"
                        color="orange"
                    />
                </div>
                <Centreon.HorizontalLine />
                <div class="popup-body">
                    <Centreon.Description date={`Last update ${modalDetails.last_update}`} />
                    <Centreon.Description title="Description:" />
                    <Centreon.Description text={modalDetails.description} />
                </div>
                <Centreon.HorizontalLine />
                <div className="popup-footer">
                    <Centreon.Description note={modalDetails.release_note} />
                </div>
                <Centreon.IconClose iconType="big"  onClick={onCloseClicked}/>
            </Centreon.Popup>
        )
    }
}

export default ExtensionDetailPopup;