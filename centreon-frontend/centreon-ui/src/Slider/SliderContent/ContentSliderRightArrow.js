import React from "react";

const ContentSliderRightArrow = ({ goToNextSlide, iconColor }) => {
  return (
    <span className="content-slider-next" onClick={goToNextSlide}>
      <span className={`content-slider-next-icon ${iconColor ? iconColor : ''}`} />
    </span>
  );
};

export default ContentSliderRightArrow;
