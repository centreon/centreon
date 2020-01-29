/* eslint-disable react/jsx-filename-extension */
/* eslint-disable jsx-a11y/no-static-element-interactions */
/* eslint-disable jsx-a11y/click-events-have-key-events */
/* eslint-disable react/prop-types */

import React from 'react';
import clsx from 'clsx';
import styles from './custom-icon-with-text.scss';
import defaultImage from '../../../img/icons/small-logo.png';

const CustomIconWithText = ({ label, image, onClick, iconOff }) => {
  return (
    <span className={clsx(styles['custom-multiple'])} onClick={onClick}>
      <img
        className={clsx(styles['custom-multiple-icon'])}
        {...(!iconOff ? { src: image || defaultImage } : {})}
        alt=""
      />
      <span
        style={{
          paddingLeft: '6px',
        }}
        className={clsx(styles['custom-multiple-text'])}
      >
        {label}
      </span>
    </span>
  );
};

export default CustomIconWithText;
