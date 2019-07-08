/* eslint-disable react/jsx-filename-extension */
/* eslint-disable jsx-a11y/no-static-element-interactions */
/* eslint-disable jsx-a11y/click-events-have-key-events */
/* eslint-disable react/prop-types */

import React from 'react';
import classnames from 'classnames';
import styles from './close-icon.scss';

const IconClose = ({ iconType, iconPosition, onClick, customStyle }) => (
  <span
    onClick={onClick}
    className={classnames(
      styles['icon-close'],
      { [styles[`icon-close-${iconType}`]]: true },
      styles[iconPosition || ''],
      styles[customStyle || ''],
    )}
  />
);

export default IconClose;
