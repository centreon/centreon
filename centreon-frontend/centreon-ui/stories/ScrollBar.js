import React from "react";
import { storiesOf } from "@storybook/react";
import {ScrollBar, Popup, MessageError, IconClose} from "../src";

storiesOf("Scroll", module).add(
  "Scrollbar - custom",
  () => 
    <Popup popupType="small">
      <div class="popup-header blue">
        <h3 className="popup-header-title">Popup Header</h3>
      </div>
      <ScrollBar>
        <div class="popup-body">
          <p className="description-text">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Aliquam lobortis faucibus tellus. Phasellus in felis sed elit hendrerit facilisis eget sollicitudin ante. Mauris suscipit porttitor semper. Aenean laoreet risus diam, in aliquam ante laoreet in. Nulla mollis velit dolor, vitae sagittis eros auctor in. Phasellus id tincidunt lacus, et elementum eros. Phasellus id commodo risus. Quisque sagittis cursus eros et ornare.
          Aenean at magna arcu. Curabitur fringilla eu quam et aliquet. Nam sed libero semper, pellentesque justo sit amet, tempus sapien. Donec viverra nisi at sapien semper hendrerit. Nunc sed fermentum dolor, at varius leo. Donec ullamcorper dui at tincidunt facilisis. Praesent a pretium nisi. Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>
          <p className="description-text">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Aliquam lobortis faucibus tellus. Phasellus in felis sed elit hendrerit facilisis eget sollicitudin ante. Mauris suscipit porttitor semper. Aenean laoreet risus diam, in aliquam ante laoreet in. Nulla mollis velit dolor, vitae sagittis eros auctor in. Phasellus id tincidunt lacus, et elementum eros. Phasellus id commodo risus. Quisque sagittis cursus eros et ornare.
          Aenean at magna arcu. Curabitur fringilla eu quam et aliquet. Nam sed libero semper, pellentesque justo sit amet, tempus sapien. Donec viverra nisi at sapien semper hendrerit. Nunc sed fermentum dolor, at varius leo. Donec ullamcorper dui at tincidunt facilisis. Praesent a pretium nisi. Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>
        </div>
      </ScrollBar>
      <div className="popup-footer m-0">
        <MessageError
          messageError="red"
          text="Generation of configuration has failed, please try again."
        />
      </div>
      <IconClose iconType="middle" />
    </Popup>,
  { notes: "A very simple component" }
);
