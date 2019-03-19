import React from "react";
import "./content-horizontal-line.scss";

const HorizontalLineContent = ({ hrTitle }) => (
  <div className="content-hr">
    <span className="content-hr-title">{hrTitle}</span>
  </div>
);

export default HorizontalLineContent;