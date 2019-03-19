import React from "react";

const ContentSliderLeftArrow = ({ goToPrevSlide, iconColor }) => {
  return (
    <span className="content-slider-prev" onClick={goToPrevSlide}>
      <span className={`content-slider-prev-icon ${iconColor ? iconColor : ''}`} />
    </span>
  );
};

export default ContentSliderLeftArrow;