import React from "react";
import "./close-icon.scss";

const IconClose = ({ iconType, onClick }) => (
  <span onClick={onClick} className={`icon-close icon-close-${iconType}`} />
);

export default IconClose;
