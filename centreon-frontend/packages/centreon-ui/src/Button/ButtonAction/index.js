/* eslint-disable react/jsx-filename-extension */
/* eslint-disable jsx-a11y/no-static-element-interactions */
/* eslint-disable jsx-a11y/click-events-have-key-events */
/* eslint-disable react/prop-types */

import React from 'react';

import clsx from 'clsx';

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
  const cn = clsx(
    styles['button-action'],
    {
      [styles[`button-action-${buttonActionType || ''}`]]: true,
    },
    styles[customPosition || ''],
    styles[iconColor],
  );

  return (
    <span className={cn} onClick={onClick}>
      <IconAction iconActionType={buttonIconType} iconColor={iconColor || ''} />
      {title && <span className={styles['button-action-title']}>{title}</span>}
    </span>
  );
};

export default ButtonAction;
