import React from "react";
import "./icon-header.scss";

const IconHeader = ({ iconType, iconName, style, onClick }) => {
  return (
    <span className="icons-wrap" style={style}>
      <span onClick={onClick} className={`iconmoon icon-${iconType}`} />
      <span className="icon__name">{iconName}</span>
    </span>
  );
};

export default IconHeader;
