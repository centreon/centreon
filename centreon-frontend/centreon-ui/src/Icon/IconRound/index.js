import React from "react";
import "./icon-round.scss";

const IconRound = ({ iconColor, iconType, iconTitle }) => {
  return (
    <span className={`icons icons-round ${iconColor}`}>
      <span className={`iconmoon icon-${iconType}`} title={iconTitle} />
    </span>
  );
};

export default IconRound;
