/* eslint-disable react/jsx-filename-extension */
/* eslint-disable jsx-a11y/no-static-element-interactions */
/* eslint-disable jsx-a11y/click-events-have-key-events */
/* eslint-disable react/prop-types */

import React from 'react';

import clsx from 'clsx';

import IconAction from '../../Icon/IconAction';

import styles from './button-action-input.scss';

const ButtonActionInput = ({
  buttonIconType,
  onClick,
  buttonColor,
  iconColor,
  buttonPosition,
}) => {
  const cn = clsx(
    styles['button-action-input'],
    styles[buttonColor || ''],
    styles[buttonPosition || ''],
  );
  return (
    <span className={cn} onClick={onClick}>
      <IconAction iconActionType={buttonIconType} iconColor={iconColor || ''} />
    </span>
  );
};

export default ButtonActionInput;
