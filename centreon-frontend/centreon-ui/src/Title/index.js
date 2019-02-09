import React from "react";
import "./custom-title.scss";

const Title = ({ icon, label, titleColor }) => (
  <h2 className="custom-title">
    {icon ? (
      <span className={`custom-title-icon custom-title-icon-${icon}`} />
    ) : null}
    <span className={`custom-title-label ${titleColor ? titleColor : ''}`}>{label}</span>
  </h2>
);

export default Title;
