import React from 'react';
import './icon-header.scss';

const IconHeader = ({iconType, iconName, style}) => {
  return (
    <span className="icons-wrap" style={style}>
      <span className={`iconmoon icon-${iconType}`}></span>
      <span className="icon__name">{iconName}</span>
    </span>
  )
}

export default IconHeader;