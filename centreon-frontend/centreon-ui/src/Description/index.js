import React from "react";
import "./content-description.scss";

const DescriptionContent = ({ date, title, text, note }) => (
  <React.Fragment>
    {date ? <span className="content-description-date">{date}</span> : null}
    {title ? <h3 className="content-description-title">{title}</h3> : null}
    {text ? (
      <p className="content-description-text">
        {text.split("\n").map(i => {
          return (
            <span>
              {i}
              <br />
            </span>
          );
        })}
      </p>
    ) : null}
    {note ? (
      <span className="content-description-release-note">{note}</span>
    ) : null}
  </React.Fragment>
);

export default DescriptionContent;