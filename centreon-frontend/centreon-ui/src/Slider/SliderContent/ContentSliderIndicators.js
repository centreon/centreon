import React from 'react';

const ContentSliderIndicators = ({images, currentIndex, handleDotClick}) => {
  return (
    <div className="content-slider-indicators">
      {images.map((image, i) => (
        <span 
          className={`${i === currentIndex ? 'active' : 'dot'}`} 
          onClick={handleDotClick}
          data-index={i}
          key={i}
        />
      ))
      }
    </div>
  )
}

export default ContentSliderIndicators;