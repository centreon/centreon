import React from "react";
import IconAction from "../../Icon/IconAction";
import "./button-action.scss";

const ButtonAction = ({ buttonActionType, buttonIconType, onClick, iconColor, title }) => (
  <span
    className={`button-action button-action-${buttonActionType} ${iconColor}`}
    onClick={onClick}
  >
    <IconAction iconColor={iconColor ? iconColor : ''} iconActionType={buttonIconType} />
    {title && <span className="button-action-title">{title}</span>}
  </span>
);

export default ButtonAction;
