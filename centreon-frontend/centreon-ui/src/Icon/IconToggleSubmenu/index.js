import React from 'react';
import './icon-toggle-submenu.scss';

const IconToggleSubmenu = ({iconType}) => {
  return (
    <span className={`icons-toggle-${iconType}`}></span>
  )
}

export default IconToggleSubmenu;