import React from "react";
import IconAction from "../../Icon/IconAction";
import "./button.scss";

const Button = ({ label, onClick, buttonType, color, iconActionType }) => (
  <button
    className={`button button-${buttonType}-${color} linear`}
    onClick={onClick}
  >
    {iconActionType ? <IconAction iconActionType={iconActionType} /> : null}
    {label}
  </button>
);

export default Button;
