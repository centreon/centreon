import React from "react";
import "./content-icons.scss";

const IconContent = ({ iconContentType, iconContentColor, loading }) => (
  <span
    style={
      loading ? { top: '20%' } : {}
    }
    className={`content-icon content-icon-${iconContentType} ${iconContentColor ? `content-icon-${iconContentType}-${iconContentColor}` : ''} ${loading ? 'loading-animation' : ''}`}
  />
);

export default IconContent;
