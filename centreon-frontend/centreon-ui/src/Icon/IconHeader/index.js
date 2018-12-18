import React from 'react';
import './icon-header.scss';

const IconHeader = ({iconType, iconName, style}) => {
  return (
    <span class="icons-wrap" style={style}>
      <span class={`iconmoon icon-${iconType}`}></span>
      <span class="icon__name">{iconName}</span>
    </span>
  )
}

export default IconHeader;