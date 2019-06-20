import React from "react";
import classnames from 'classnames';
import styles from './action-icons.scss';

const IconAction = ({ iconActionType, iconColor, iconDirection, customStyle, iconReset, ...rest }) => {
  const cn = classnames(styles["icon-action"], {[styles[`icon-action-${iconActionType}`]]: true}, styles[iconColor ? iconColor : ''], styles[iconDirection ? iconDirection : ''], styles[customStyle ? customStyle : ''], styles[iconReset ? iconReset : '']);
  return(
    <span className={cn} {...rest}/>
  )
};

export default IconAction;