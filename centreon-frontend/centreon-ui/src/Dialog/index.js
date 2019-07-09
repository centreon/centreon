/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */
/* eslint-disable react/prefer-stateless-function */

import React, { Component } from 'react';
import classnames from 'classnames';
import PopupNew from '../Popup/PopupNew';
import IconCloseNew from '../MaterialComponents/Icons/IconClose';
import styles2 from '../Popup/PopupNew/popup.scss';

class Dialog extends Component {
  render() {
    const { active, onClose, buttons, info, children, header } = this.props;
    return active ? (
      <PopupNew popupType="small">
        <div className={classnames(styles2['popup-header'])}>{header}</div>
        <div className={classnames(styles2['popup-body'])}>
          <p className={classnames(styles2['popup-info'])}>{info}</p>
          {children}
          {buttons}
        </div>
        <IconCloseNew onClick={onClose} />
      </PopupNew>
    ) : null;
  }
}

export default Dialog;
