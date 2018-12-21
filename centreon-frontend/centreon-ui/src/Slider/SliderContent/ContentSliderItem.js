import React, { Component } from "react";

class ContentSliderItem extends Component {
  render() {
    const { sliderImage } = this.props;
    return (
      <div className="content-slider-item">
        {sliderImage ? (
          <img
            src={sliderImage}
            alt="Slider image"
            className="content-slider-item-image img-responsive"
          />
        ) : null}
      </div>
    );
  }
}

export default ContentSliderItem;
