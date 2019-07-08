/* eslint-disable react/jsx-filename-extension */
/* eslint-disable jsx-a11y/no-static-element-interactions */
/* eslint-disable jsx-a11y/click-events-have-key-events */
/* eslint-disable react/prop-types */

import React from 'react';
import classnames from 'classnames';
import styles from './custom-icon-with-text.scss';

const CustomIconWithText = ({ label, image, onClick }) => {
  return (
    <span className={classnames(styles['custom-multiple'])} onClick={onClick}>
      <span
        className={classnames(styles['custom-multiple-icon'])}
        style={{ backgroundImage: `url('${image}')` }}
      />
      <span className={classnames(styles['custom-multiple-text'])}>
        {label}
      </span>
    </span>
  );
};

export default CustomIconWithText;
