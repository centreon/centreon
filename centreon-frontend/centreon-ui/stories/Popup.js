import React from "react";
import { storiesOf } from "@storybook/react";
import { Popup, IconClose } from "../src";

storiesOf("Popup", module).add(
  "Popup - small",
  () => (
    <Popup popupType="small">
      <div class="popup-header">
        <h3>Popup Header</h3>
      </div>
      <div class="popup-body">
        <p>Popup body</p>
      </div>
      <div className="popup-footer">
        <p>Popup footer</p>
      </div>
      <IconClose iconType="middle" />
    </Popup>
  ),
  { notes: "A very simple component" }
);

storiesOf("Popup", module).add(
  "Popup - big",
  () => (
    <Popup popupType="big">
      <div class="popup-header">
        <h3>Popup Header</h3>
      </div>
      <div class="popup-body">
        <p>Popup body</p>
      </div>
      <div className="popup-footer">
        <p>Popup footer</p>
      </div>
      <IconClose iconType="big" />
    </Popup>
  ),
  { notes: "A very simple component" }
);
