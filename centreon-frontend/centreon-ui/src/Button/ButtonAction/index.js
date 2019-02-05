import React from "react";
import IconAction from "../../Icon/IconAction";
import "./button-action.scss";

const ButtonAction = ({ buttonActionType, buttonIconType, onClick, iconColor }) => (
  <span
    className={`button-action button-action-${buttonActionType} ${iconColor}`}
    onClick={onClick}
  >
    <IconAction iconColor={iconColor ? iconColor : ''} iconActionType={buttonIconType} />
  </span>
);

export default ButtonAction;
