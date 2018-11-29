import React from "react";
import "./content-icons.scss";

const IconContent = ({ iconContentType, iconContentColor }) => (
  <span
    class={`content-icon content-icon-${iconContentType} content-icon-add-${iconContentColor}`}
  />
);

export default IconContent;
