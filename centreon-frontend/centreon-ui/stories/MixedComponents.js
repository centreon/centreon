import React from "react";
import { storiesOf } from "@storybook/react";
import { IconInfo, InputField } from "../src";

storiesOf("Mixed Components", module).add(
  "Mixed Component - BAM",
  () => (
    <div className="container__row">
      <div className="container__col-md-3">
        <div className="container__row">
          <div className="container__col-md-4 center-vertical m-0">
            <IconInfo iconColor="gray" iconName="question" iconText="Notification interval" />
          </div>
          <div className="container__col-md-8 m-0 center-vertical">
            <InputField 
              type="text"
              inputSize="smallest m-0" 
            />
            <IconInfo iconText="*60 seconds" />
          </div>
        </div>
      </div>
    </div>
    
  ),
  { notes: "A very simple component" }
);