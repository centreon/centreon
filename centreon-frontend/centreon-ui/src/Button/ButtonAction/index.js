/* eslint-disable react/jsx-filename-extension */
/* eslint-disable jsx-a11y/no-static-element-interactions */
/* eslint-disable jsx-a11y/click-events-have-key-events */
/* eslint-disable react/prop-types */

import React from 'react';
import classnames from 'classnames';
import IconAction from '../../Icon/IconAction';
import styles from './button-action.scss';

const ButtonAction = ({
  buttonActionType,
  buttonIconType,
  onClick,
  iconColor,
  title,
  customPosition,
}) => {
  const cn = classnames(
    styles['button-action'],
    {
      [styles[`button-action-${buttonActionType || ''}`]]: true,
    },
    styles[customPosition || ''],
    styles[iconColor],
  );
  return (
    <span className={cn} onClick={onClick}>
      <IconAction iconColor={iconColor || ''} iconActionType={buttonIconType} />
      {title && <span className={styles['button-action-title']}>{title}</span>}
    </span>
  );
};

export default ButtonAction;
