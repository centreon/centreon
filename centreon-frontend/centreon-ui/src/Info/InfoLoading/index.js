import React from 'react';
import IconAction from '../../Icon/IconAction';
import './info-loading.scss';

const InfoLoading = ({infoType, color, customClass, label, iconActionType, iconColor}) => {
  return (
    <span 
      className={`info-loading info-loading-${infoType}-${color} linear ${
        customClass ? customClass : ''
      }`}
    >
      {iconActionType ? <IconAction iconColor={iconColor} iconActionType={iconActionType} /> : ''}
      {label}
      <span className="info-loading-icon"></span>
    </span>
  )
}

export default InfoLoading;