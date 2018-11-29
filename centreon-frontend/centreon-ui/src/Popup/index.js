import React from "react";
import "./popup.scss";

const Popup = ({ popupType, children }) => {
  return (
    <React.Fragment>
      <div class={`popup popup-${popupType}`}>
        <div class="popup-dialog">
          <div class="popup-content">{children}</div>
        </div>
      </div>
      <div class="popup-overlay" />
    </React.Fragment>
  );
};

export default Popup;
