import React from "react";
import IconAction from "../../Icon/IconAction";
import "./button-action-input.scss";

const ButtonActionInput = ({ buttonActionType, buttonIconType, onClick, buttonColor, iconColor }) => (
  <span
    className={`button-action-input button-action-input-${buttonActionType} ${buttonColor}`}
    onClick={onClick}
  >
    <IconAction iconColor={iconColor ? iconColor : ''} iconActionType={buttonIconType} />
  </span>
);

export default ButtonActionInput;
