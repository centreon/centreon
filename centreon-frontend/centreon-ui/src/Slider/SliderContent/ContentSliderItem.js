import React from "react";

const ContentSliderItem = ({image, isActive}) => {

  const styles = {
    backgroundImage: `url(${image})`,
    backgroundSize: 'cover',
    backgroundRepeat: 'no-repeat',
    backgroundPosition: '50% 60%'
  }

  return (
    <div
      alt="Slider image"
      className={`content-slider-item ${isActive ? 'active-slide' : ''}`}
      style={styles}
    />
  );
}

export default ContentSliderItem;
