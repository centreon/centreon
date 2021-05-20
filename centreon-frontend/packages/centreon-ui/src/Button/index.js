/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/button-has-type */
/* eslint-disable react/prop-types */

import React from 'react';

import clsx from 'clsx';

import IconAction from '../Icon/IconAction';

import styles from './button.scss';

const Button = ({
  children,
  label,
  onClick,
  buttonType,
  color,
  iconActionType,
  customClass,
  customSecond,
  style,
  iconColor,
  iconPosition,
  position,
  ...rest
}) => {
  const cn = clsx(
    styles.button,
    { [styles[`button-${buttonType}-${color}`]]: true },
    styles.linear,
    styles[customClass || ''],
    styles[customSecond || ''],
    styles[`button-${iconPosition}`],
    styles[position || ''],
  );

  return (
    <button className={cn} style={style} onClick={onClick} {...rest}>
      {iconActionType ? (
        <IconAction
          iconActionType={iconActionType}
          iconColor={iconColor}
          iconDirection="icon-position-right"
        />
      ) : null}
      {label}
      {children}
    </button>
  );
};

export default Button;
