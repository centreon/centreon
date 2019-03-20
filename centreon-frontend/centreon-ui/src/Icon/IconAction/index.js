import React from "react";
import "./action-icons.scss";

const IconAction = ({ iconActionType, iconColor, ...rest }) => (
  <span className={`icon-action icon-action-${iconActionType} ${iconColor ? iconColor : ''}`} {...rest}/>
);

export default IconAction;
