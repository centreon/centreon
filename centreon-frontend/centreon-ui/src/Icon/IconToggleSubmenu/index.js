import React from 'react';
import './icon-toggle-submenu.scss';

const IconToggleSubmenu = ({iconType}) => {
  return (
    <span class={`icons-toggle-${iconType}`}></span>
  )
}

export default IconToggleSubmenu;