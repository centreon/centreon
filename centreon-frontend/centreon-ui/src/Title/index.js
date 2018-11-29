import React from "react";
import "./custom-title.scss";

const Title = ({ icon, label }) => (
  <h2 class="custom-title">
    {icon ? (
      <span class={`custom-title-icon custom-title-icon-${icon}`} />
    ) : null}
    {label}
  </h2>
);

export default Title;
