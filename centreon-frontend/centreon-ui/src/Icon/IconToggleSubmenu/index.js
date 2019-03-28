import React from "react";
import classnames from 'classnames';
import styles from './icon-toggle-submenu.scss';

const IconToggleSubmenu = ({ iconType, iconPosition, ...rest }) => {
  const cn = classnames({[styles[`icons-toggle-${iconType}`]]: true}, styles[iconPosition ? iconPosition : '']);
  return <span className={cn} {...rest} />;
};

export default IconToggleSubmenu;