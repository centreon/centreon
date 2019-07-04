import React from "react";
import IconAction from "../../Icon/IconAction";
import classnames from "classnames";
import styles from "./button.scss";

const Button = ({
  children,
  label,
  onClick,
  buttonType,
  color,
  iconActionType,
  customClass,
  customSecond,
  style,
  iconColor,
  iconPosition,
  position,
  ...rest
}) => {
  const cn = classnames(
    styles.button,
    { [styles[`button-${buttonType}-${color}`]]: true },
    styles.linear,
    styles[customClass ? customClass : ""],
    styles[customSecond ? customSecond : ""],
    styles[`button-${iconPosition}`],
    styles[position ? position : ""]
  );

  return (
    <button className={cn} onClick={onClick} style={style} {...rest}>
      {iconActionType ? (
        <IconAction
          iconDirection="icon-position-right"
          iconColor={iconColor}
          iconActionType={iconActionType}
        />
      ) : null}
      {label}
      {children}
    </button>
  );
};

export default Button;
