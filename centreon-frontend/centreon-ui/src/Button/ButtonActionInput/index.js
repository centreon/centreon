import React from "react";
import IconAction from "../../Icon/IconAction";
import classnames from 'classnames';
import styles from './button-action-input.scss';

const ButtonActionInput = ({ buttonIconType, onClick, buttonColor, iconColor, buttonPosition }) => {
  const cn = classnames(styles["button-action-input"], styles[buttonColor ? buttonColor : ''], styles[buttonPosition ? buttonPosition : '']);
  return (
    <span
      className={cn}
      onClick={onClick}
    >
      <IconAction iconColor={iconColor ? iconColor : ''} iconActionType={buttonIconType} />
    </span>
  )
};

export default ButtonActionInput;
