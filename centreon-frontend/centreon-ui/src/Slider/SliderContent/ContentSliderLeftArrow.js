import React from "react";
import classnames from "classnames";
import styles from "./content-slider.scss";

const ContentSliderLeftArrow = ({ goToPrevSlide, iconColor }) => {
  return (
    <span
      className={classnames(styles["content-slider-prev"])}
      onClick={goToPrevSlide}
    >
      <span
        className={classnames(
          styles["content-slider-prev-icon"],
          styles[iconColor ? iconColor : ""]
        )}
      />
    </span>
  );
};

export default ContentSliderLeftArrow;
