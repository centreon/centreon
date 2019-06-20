/* eslint-disable react/prop-types */
/* eslint-disable react/no-array-index-key */
/* eslint-disable jsx-a11y/click-events-have-key-events */
/* eslint-disable jsx-a11y/no-static-element-interactions */

import React from 'react';
import classnames from 'classnames';
import styles from './content-slider.scss';

function ContentSliderIndicators({ images, currentIndex, handleDotClick }) {
  return (
    <div className={classnames(styles['content-slider-indicators'])}>
      {images.map((image, i) => (
        <span
          className={classnames(styles[i === currentIndex ? 'active' : 'dot'])}
          onClick={handleDotClick}
          data-index={i}
          key={i}
        />
      ))}
    </div>
  );
}

export default ContentSliderIndicators;
