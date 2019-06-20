/* eslint-disable react/prop-types */
/* eslint-disable react/button-has-type */

import React from 'react';
import classnames from 'classnames';
import IconAction from '../../Icon/IconAction';
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
  const cn = classnames(
    styles.button,
    { [styles[`button-${buttonType}-${color}`]]: true },
    styles.linear,
    styles[customClass || ''],
    styles[customSecond || ''],
    styles[`button-${iconPosition}`],
    styles[position || ''],
  );

  return (
    <button className={cn} onClick={onClick} style={style} {...rest}>
      {iconActionType ? (
        <IconAction
          iconDirection="icon-position-right"
          iconColor={iconColor}
          iconActionType={iconActionType}
        />
      ) : null}
      {label}
      {children}
    </button>
  );
};

export default Button;
