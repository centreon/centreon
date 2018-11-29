import React from "react";
import "./switcher.scss";

const Switcher = ({ switcherTitle, switcherStatus }) => (
  <div class="switcher">
    <span class="switcher-title">{switcherTitle}</span>
    <span class="switcher-status">{switcherStatus}</span>
    <label class="switch">
      <input type="checkbox" />
      <span class="switch-slider switch-round" />
    </label>
  </div>
);

export default Switcher;
