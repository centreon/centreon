/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */

import React from 'react';
import classnames from 'classnames';
import styles from './action-icons.scss';

const IconAction = ({
  iconActionType,
  iconColor,
  iconDirection,
  customStyle,
  iconReset,
  ...rest
}) => {
  const cn = classnames(
    styles['icon-action'],
    { [styles[`icon-action-${iconActionType}`]]: true },
    styles[iconColor || ''],
    styles[iconDirection || ''],
    styles[customStyle || ''],
    styles[iconReset || ''],
  );
  return <span className={cn} {...rest} />;
};

export default IconAction;
