import React from "react";
import "./popup.scss";

const Popup = ({ popupType, children }) => {
  return (
    <React.Fragment>
      <div className={`popup popup-${popupType}`}>
        <div className="popup-dialog">
          <div className="popup-content">{children}</div>
        </div>
      </div>
      <div className="popup-overlay" />
    </React.Fragment>
  );
};

export default Popup;
