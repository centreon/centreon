/* eslint-disable no-dupe-keys */
/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */
/* eslint-disable react/prefer-stateless-function */

import React, { Component } from 'react';
import classnames from 'classnames';
import ButtonCustom from '@material-ui/core/Button';
import PopupNew from '../../Popup/PopupNew';
import IconCloseNew from '../../MaterialComponents/Icons/IconClose';
import styles2 from '../../Popup/PopupNew/popup.scss';

class ConfirmationDialog extends Component {
  render() {
    const {
      active,
      onClose,
      onYesClicked,
      info,
      onNoClicked,
      header,
    } = this.props;
    return active ? (
      <PopupNew popupType="small">
        <div className={classnames(styles2['popup-header'])}>
          {header ? (
            <h3 className={classnames(styles2['popup-title'])}>{header}</h3>
          ) : null}
        </div>
        <div className={classnames(styles2['popup-body'])}>
          <p className={classnames(styles2['popup-info'])}>{info}</p>
          <ButtonCustom
            variant="contained"
            color="primary"
            style={{
              backgroundColor: '#0072CE',
              fontSize: 11,
              textAlign: 'center',
              border: '1px solid #0072CE',
            }}
            onClick={onYesClicked}
          >
            Yes
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
            No
          </ButtonCustom>
        </div>
        <IconCloseNew onClick={onClose} />
      </PopupNew>
    ) : null;
  }
}

export default ConfirmationDialog;
