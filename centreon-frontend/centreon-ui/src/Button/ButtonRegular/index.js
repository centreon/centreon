import React from "react";
import IconAction from "../../Icon/IconAction";
import "./button.scss";

const Button = ({
  children,
  label,
  onClick,
  buttonType,
  color,
  iconActionType,
  customClass,
  style
}) => (
  <button
    className={`button button-${buttonType}-${color} linear ${
      customClass ? customClass : null
    }`}
    onClick={onClick}
    style={style}
  >
    {iconActionType ? <IconAction iconActionType={iconActionType} /> : null}
    {label}
    {children}
  </button>
);

export default Button;
