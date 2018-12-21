import React from "react";
import "./content-icons.scss";

const IconContent = ({ iconContentType, iconContentColor }) => (
  <span
    className={`content-icon content-icon-${iconContentType} content-icon-add-${iconContentColor}`}
  />
);

export default IconContent;
