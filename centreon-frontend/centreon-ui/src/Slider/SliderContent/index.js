import React from "react";
import ContentSliderItem from "./ContentSliderItem";
import "./content-slider.scss";

const SliderContent = ({}) => (
  <div className="content-slider">
    <div className="content-slider-indicators">
      <span />
      <span className="active" />
      <span />
    </div>
    <div className="content-slider-items">
      <ContentSliderItem sliderImage="https://static.centreon.com/wp-content/uploads/2018/09/plugin-banner-it-operations-management.png" />
    </div>
    <div className="content-slider-controls">
      <span className="content-slider-prev">
        <span className="content-slider-prev-icon" />
      </span>
      <span className="content-slider-next">
        <span className="content-slider-next-icon" />
      </span>
    </div>
  </div>
);

export default SliderContent;
