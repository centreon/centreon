import React from "react";
import "./action-icons.scss";

const IconAction = ({ iconActionType, iconColor }) => (
  <span className={`icon-action icon-action-${iconActionType} ${iconColor ? iconColor : ''}`} />
);

export default IconAction;
