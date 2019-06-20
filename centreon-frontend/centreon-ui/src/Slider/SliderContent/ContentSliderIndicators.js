import React from "react";
import classnames from 'classnames';
import styles from './content-slider.scss';

const ContentSliderIndicators = ({ images, currentIndex, handleDotClick }) => {
  return (
    <div className={classnames(styles["content-slider-indicators"])}>
      {images.map((image, i) => (
        <span
          className={classnames(styles[i === currentIndex ? "active" : "dot"])}
          onClick={handleDotClick}
          data-index={i}
          key={i}
        />
      ))}
    </div>
  );
};

export default ContentSliderIndicators;