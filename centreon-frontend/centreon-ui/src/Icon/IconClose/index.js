import React from "react";
import "./close-icon.scss";

const IconClose = ({ iconType }) => (
  <span class={`icon-close icon-close-${iconType}`} />
);

export default IconClose;
