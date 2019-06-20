/* eslint-disable react/prop-types */

import React from 'react';
import classnames from 'classnames';
import styles2 from './content-slider.scss';

function ContentSliderItem({ image, isActive }) {
  return (
    <div
      alt="Slider image"
      className={classnames(
        styles2['content-slider-item'],
        styles2[isActive ? 'active-slide' : ''],
      )}
      style={{
        backgroundImage: `url(${image})`,
      }}
    />
  );
}

export default ContentSliderItem;
