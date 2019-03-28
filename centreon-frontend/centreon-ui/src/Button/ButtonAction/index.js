import React from "react";
import IconAction from "../../Icon/IconAction";
import classnames from 'classnames';
import styles from './button-action.scss';

const ButtonAction = ({ buttonActionType, buttonIconType, onClick, iconColor, title, customPosition }) => {
  const cn = classnames(styles["button-action"], {[styles[`button-action-${buttonActionType ? buttonActionType : ''}`]]: false}, styles[customPosition ? customPosition : ''], styles[iconColor]);
  return (
    <span
      className={cn}
      onClick={onClick}
    >
      <IconAction iconColor={iconColor ? iconColor : ''} iconActionType={buttonIconType} />
      {title && <span className="button-action-title">{title}</span>}
    </span>
  )
};

export default ButtonAction;
