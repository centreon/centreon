import React from 'react';
import IconAction from "../IconAction";
import './icon-legend.scss';

const IconLegend = ({iconColor, buttonIconType, title, legendType}) => {
  return (
    <span className={`icon-legend ${legendType ? legendType : ''}`}>
      <IconAction iconColor={iconColor ? iconColor : ''} iconActionType={buttonIconType} />
      {title && <span className="icon-legend-title">{title}</span>}
    </span>
  )
}

export default IconLegend;