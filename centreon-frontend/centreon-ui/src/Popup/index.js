/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */

import React from 'react';
import clsx from 'clsx';
import styles from './popup.scss';

const Popup = ({ popupType, children, customClass }) => {
  return (
    <>
      <div
        className={clsx(
          styles.popup,
          { [styles[`popup-${popupType}`]]: true },
          styles[customClass || ''],
        )}
      >
        <div className={clsx(styles['popup-dialog'])}>
          <div className={clsx(styles['popup-content'])}>{children}</div>
        </div>
      </div>
      <div className={clsx(styles['popup-overlay'])} />
    </>
  );
};

export default Popup;
