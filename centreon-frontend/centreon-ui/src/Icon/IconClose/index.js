import React from "react";
import classnames from 'classnames';
import styles from "./close-icon.scss";

const IconClose = ({ iconType, iconPosition, onClick, customStyle }) => (
  <span onClick={onClick} className={classnames(styles["icon-close"], {[styles[`icon-close-${iconType}`]]: true}, styles[iconPosition ? iconPosition : ''], styles[customStyle ? customStyle : ''])} />
);

export default IconClose;