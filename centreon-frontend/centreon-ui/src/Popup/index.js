import React from "react";
import classnames from 'classnames';
import styles from './popup.scss';

const Popup = ({ popupType, children, customClass }) => {
  return (
    <React.Fragment>
      <div className={classnames(styles.popup, {[styles[`popup-${popupType}`]]: true}, styles[customClass ? customClass : ''])}>
        <div className={classnames(styles["popup-dialog"])}>
          <div className={classnames(styles["popup-content"])}>{children}</div>
        </div>
      </div>
      <div className={classnames(styles["popup-overlay"])} />
    </React.Fragment>
  );
};

export default Popup;