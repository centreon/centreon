import React from "react";
import IconAction from "../../Icon/IconAction";
import "./button-action.scss";

const ButtonAction = ({ buttonActionType, buttonIconType, onClick }) => (
  <span
    className={`button-action button-action-${buttonActionType}`}
    onClick={onClick}
  >
    <IconAction iconActionType={buttonIconType} />
  </span>
);

export default ButtonAction;
