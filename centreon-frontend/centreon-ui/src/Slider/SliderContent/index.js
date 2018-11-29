import React from "react";
import ContentSliderItem from "./ContentSliderItem";
import "./content-slider.scss";

const SliderContent = ({}) => (
  <div class="content-slider">
    <div class="content-slider-indicators">
      <span />
      <span class="active" />
      <span />
    </div>
    <div class="content-slider-items">
      <ContentSliderItem sliderImage="https://static.centreon.com/wp-content/uploads/2018/09/plugin-banner-it-operations-management.png" />
    </div>
    <div class="content-slider-controls">
      <span class="content-slider-prev">
        <span class="content-slider-prev-icon" />
      </span>
      <span class="content-slider-next">
        <span class="content-slider-next-icon" />
      </span>
    </div>
  </div>
);

export default SliderContent;
