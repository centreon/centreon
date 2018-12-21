import React from "react";
import "./switcher.scss";

const Switcher = ({ switcherTitle, switcherStatus, customClass }) => (
  <div className={`switcher ${customClass}`}>
    <span className="switcher-title">{switcherTitle ? switcherTitle : " "}</span>
    <span className="switcher-status">{switcherStatus}</span>
    <label className="switch">
      <input type="checkbox" />
      <span className="switch-slider switch-round" />
    </label>
  </div>
);

export default Switcher;
