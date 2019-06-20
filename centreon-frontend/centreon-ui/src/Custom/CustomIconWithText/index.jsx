/* eslint-disable react/prop-types */
/* eslint-disable jsx-a11y/click-events-have-key-events */
/* eslint-disable jsx-a11y/no-static-element-interactions */
/* eslint-disable react/jsx-filename-extension */

import React from 'react';
import classnames from 'classnames';
import styles from './custom-icon-with-text.scss';

function CustomIconWithText({ label, image, onClick }) {
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
}

export default CustomIconWithText;
