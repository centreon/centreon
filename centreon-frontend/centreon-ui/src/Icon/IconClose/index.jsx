/* eslint-disable react/prop-types */
/* eslint-disable jsx-a11y/no-static-element-interactions */
/* eslint-disable jsx-a11y/click-events-have-key-events */

import React from 'react';
import classnames from 'classnames';
import styles from './close-icon.scss';

function IconClose({ iconType, iconPosition, onClick, customStyle }) {
  return (
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
}

export default IconClose;
