import React from 'react';
import './icon-toggle-submenu.scss';

const IconToggleSubmenu = ({iconType, ...rest}) => {
  return (
    <span className={`icons-toggle-${iconType}`} {...rest} ></span>
  )
}

export default IconToggleSubmenu;