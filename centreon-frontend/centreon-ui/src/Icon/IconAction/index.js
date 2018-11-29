import React from "react";
import "./action-icons.scss";

const IconAction = ({ iconActionType }) => (
  <span class={`icon-action icon-action-${iconActionType}`} />
);

export default IconAction;
