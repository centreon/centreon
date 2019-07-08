/* eslint-disable react/jsx-filename-extension */
/* eslint-disable jsx-a11y/no-static-element-interactions */
/* eslint-disable jsx-a11y/click-events-have-key-events */
/* eslint-disable react/prop-types */

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
