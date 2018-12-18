import React from 'react';
import './icon-round.scss';

const IconRound = ({iconColor, iconType, iconTitle}) => {
  return (
    <span class={`icons icons-round ${iconColor}`}>
      <span class={`iconmoon icon-${iconType}`} title={iconTitle}></span>
    </span>
  )
}

export default IconRound;