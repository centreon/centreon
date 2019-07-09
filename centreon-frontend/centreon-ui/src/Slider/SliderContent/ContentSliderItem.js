/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */

import React from 'react';
import classnames from 'classnames';
import styles2 from './content-slider.scss';

const ContentSliderItem = ({ image, isActive }) => (
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

export default ContentSliderItem;
