import React, { Component } from 'react';
import classnames from 'classnames';
import ButtonCustom from '@material-ui/core/Button';
import PopupNew from '../../Popup/PopupNew';
import IconCloseNew from '../../MaterialComponents/Icons/IconClose';
import styles2 from '../../Popup/PopupNew/popup.scss';
import InputField from '../../InputField';

class MassiveChangeDialog extends Component {
  state = {
    warning: 0,
    critical: 0,
  };

  onWarningChanged = ({ target }) => {
    if (target.value <= 100 && target.value >= 0) {
      this.setState({
        warning: target.value,
      });
    }
  };

  onCriticalChanged = ({ target }) => {
    if (target.value <= 100 && target.value >= 0) {
      this.setState({
        critical: target.value,
      });
    }
  };

  render() {
    const {
      active,
      onClose,
      onYesClicked,
      info,
      onNoClicked,
      header,
    } = this.props;
    const { critical, warning } = this.state;
    return active ? (
      <PopupNew popupType="small">
        <div className={classnames(styles2['popup-header'])}>
          {header ? (
            <h3 className={classnames(styles2['popup-title'])}>{header}</h3>
          ) : null}
        </div>
        <div className={classnames(styles2['popup-body'])}>
          <p className={classnames(styles2['popup-info'])}>{info}</p>
          <InputField
            type="number"
            label="Warning threshold"
            onChange={this.onWarningChanged}
            name="prompt-input"
            inputSize="big"
            value={warning}
          />
          <InputField
            type="number"
            label="Critical threshold"
            onChange={this.onCriticalChanged}
            name="prompt-input"
            inputSize="big"
            value={critical}
          />
          <ButtonCustom
            variant="contained"
            color="primary"
            style={{
              backgroundColor: '#0072CE',
              fontSize: 11,
              textAlign: 'center',
              border: '1px solid #0072CE',
            }}
            onClick={() => {
              onYesClicked({
                critical,
                warning,
              });
            }}
          >
            Appy
          </ButtonCustom>
          <ButtonCustom
            variant="contained"
            color="primary"
            style={{
              backgroundColor: '#0072CE',
              fontSize: 11,
              textAlign: 'center',
              marginLeft: 30,
              backgroundColor: 'transparent',
              color: '#0072CE',
              border: '1px solid #0072CE',
              boxSizing: 'border-box',
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

export default MassiveChangeDialog;
