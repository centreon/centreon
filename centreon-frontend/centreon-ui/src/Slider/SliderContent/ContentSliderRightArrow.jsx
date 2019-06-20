/* eslint-disable react/prop-types */
/* eslint-disable jsx-a11y/no-static-element-interactions */
/* eslint-disable jsx-a11y/click-events-have-key-events */

import React from 'react';
import classnames from 'classnames';
import styles from './content-slider.scss';

function ContentSliderRightArrow({ goToNextSlide, iconColor }) {
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
}

export default ContentSliderRightArrow;
