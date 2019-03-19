import React from "react";
import "./loader-additions.scss";

export default ({ fullContent }) => (
  <div className={`loader ${fullContent ? 'full-relative-content' : ''}`}>
    <div className="loader-inner ball-grid-pulse">
      <div />
      <div />
      <div />
      <div />
    </div>
  </div>
);