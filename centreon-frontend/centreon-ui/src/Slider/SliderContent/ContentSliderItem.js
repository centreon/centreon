import React from "react";
import classnames from 'classnames';
import styles2 from './content-slider.scss';

const ContentSliderItem = ({ cssImage, image, isActive }) => {
  const styles = {
    backgroundImage: `url(${image})`,
    backgroundSize: "cover",
    backgroundRepeat: "no-repeat",
    backgroundPosition: "50% 60%"
  };

  if (cssImage) {
    <div
      alt="Slider image"
      className={classnames(
        styles2["content-slider-item"],
        styles2[isActive ? "active-slide" : ""],
        styles2["content-slider-icon"],
        styles2[`content-slider-icon-${cssImage}`],
      )}
    />
  }

  return (
    <div
      alt="Slider image"
      className={classnames(styles2["content-slider-item"], styles2[isActive ? "active-slide" : ""])}
      style={styles}
    />
  );
};

export default ContentSliderItem;
