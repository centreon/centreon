import React from "react";
import IconAction from "../../Icon/IconAction";
import "./button-action.scss";

const ButtonAction = ({ buttonActionType, buttonIconType }) => (
  <span className={`button-action button-action-${buttonActionType}`}>
    <IconAction iconActionType={buttonIconType} />
  </span>
);

export default ButtonAction;
