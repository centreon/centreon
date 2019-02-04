import React from "react";
import "./icon-number.scss";

const IconNumber = ({ iconColor, iconType, iconNumber }) => {
  return (
    <a className={`icons icons-number ${iconType} ${iconColor}`}>
      <span className="number-wrap">
        <span className="number-count">{iconNumber}</span>
      </span>
    </a>
  );
};

export default IconNumber;
