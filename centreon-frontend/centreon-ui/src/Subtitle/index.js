import React from "react";
import "./custom-subtitles.scss";

const Subtitle = ({ label, subtitleType }) => <h4 className={`custom-subtitle ${subtitleType}`}>{label}</h4>;

export default Subtitle;
