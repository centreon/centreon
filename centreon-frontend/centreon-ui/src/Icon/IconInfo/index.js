import React from "react";
import "./info-state-icon.scss";

const IconInfo = ({ iconName, iconText, iconColor }) => {
  return (
    <React.Fragment>
      {iconName && <span className={`info info-${iconName} ${iconColor ? iconColor : ''}`} />}
      {iconText && <span className="info-text">{iconText}</span>}
    </React.Fragment>
  )
};

export default IconInfo;