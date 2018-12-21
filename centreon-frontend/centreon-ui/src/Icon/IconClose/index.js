import React from "react";
import "./close-icon.scss";

const IconClose = ({ iconType }) => (
  <span className={`icon-close icon-close-${iconType}`} />
);

export default IconClose;
