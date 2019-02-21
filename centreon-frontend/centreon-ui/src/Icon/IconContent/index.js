import React from "react";
import "./content-icons.scss";

const IconContent = ({ iconContentType, iconContentColor, loading, onClick }) => (
  <span
    style={loading ? { top: "20%" } : {}}
    className={`content-icon content-icon-${iconContentType} ${
      iconContentColor
        ? `content-icon-${iconContentColor}`
        : ""
    } ${loading ? "loading-animation" : ""}`}
    onClick={onClick}
  />
);

export default IconContent;
