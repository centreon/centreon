import React from 'react';

const ContentSliderRightArrow = ({goToNextSlide}) => {
  return (
    <span className="content-slider-next" onClick={goToNextSlide}>
      <span className="content-slider-next-icon" />
    </span>
  )
}

export default ContentSliderRightArrow;