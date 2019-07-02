import React, { Component } from "react";
import PopupNew from "../../Popup/PopupNew";
import classnames from "classnames";
import IconCloseNew from "../../MaterialComponents/Icons/IconClose";
import styles2 from "../../Popup/PopupNew/popup.scss";
import ButtonCustom from "@material-ui/core/Button";
import InputField from "../../InputField";

class PromptDialog extends Component {
  state = {
    prompt: ""
  };

  onPromptChanged = ({ target }) => {
    this.setState({
      prompt: target.value
    });
  };

  render() {
    const {
      active,
      onClose,
      onYesClicked,
      info,
      onNoClicked,
      header
    } = this.props;
    const {
      prompt
    } = this.state;
    return active ? (
      <PopupNew popupType="small">
        <div className={classnames(styles2["popup-header"])}>
          {header ? (
            <h3 className={classnames(styles2["popup-title"])}>{header}</h3>
          ) : null}
        </div>
        <div className={classnames(styles2["popup-body"])}>
          <p className={classnames(styles2["popup-info"])}>{info}</p>
          <InputField
            type="number"
            onChange={this.onPromptChanged}
            name="prompt-input"
            inputSize="big"
          />
          <ButtonCustom
            variant="contained"
            color="primary"
            style={{
              backgroundColor: "#0072CE",
              fontSize: 11,
              textAlign: "center",
              border: "1px solid #0072CE"
            }}
            onClick={()=>{
              onYesClicked(prompt)
            }}
          >
            Appy
          </ButtonCustom>
          <ButtonCustom
            variant="contained"
            color="primary"
            style={{
              backgroundColor: "#0072CE",
              fontSize: 11,
              textAlign: "center",
              marginLeft: 30,
              backgroundColor: "transparent",
              color: "#0072CE",
              border: "1px solid #0072CE",
              boxSizing: "border-box"
            }}
            onClick={onNoClicked}
          >
            Cancel
          </ButtonCustom>
        </div>
        <IconCloseNew onClick={onClose} />
      </PopupNew>
    ) : null;
  }
}

export default PromptDialog;
