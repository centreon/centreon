import React from 'react';

const ContentSliderLeftArrow = ({goToPrevSlide}) => {
  return (
    <span className="content-slider-prev" onClick={goToPrevSlide}>
      <span className="content-slider-prev-icon" />
    </span>
  )
}

export default ContentSliderLeftArrow;