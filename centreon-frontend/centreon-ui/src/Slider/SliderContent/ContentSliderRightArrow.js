import React from 'react';
import classnames from 'classnames';
import styles from './content-slider.scss';

const ContentSliderRightArrow = ({ goToNextSlide, iconColor }) => {
  return (
    <span
      className={classnames(styles['content-slider-next'])}
      onClick={goToNextSlide}
    >
      <span
        className={classnames(
          styles['content-slider-next-icon'],
          styles[iconColor || ''],
        )}
      />
    </span>
  );
};

export default ContentSliderRightArrow;
