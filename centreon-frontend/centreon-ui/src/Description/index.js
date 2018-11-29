import React from "react";
import "./content-description.scss";

const DescriptionContent = ({ date, title, text, note }) => (
  <React.Fragment>
    {date ? <span class="content-description-date">{date}</span> : null}
    {title ? <h3 class="content-description-title">{title}</h3> : null}
    {text ? <p class="content-description-text">{text}</p> : null}
    {note ? <span class="content-description-release-note">{note}</span> : null}
  </React.Fragment>
);

export default DescriptionContent;
