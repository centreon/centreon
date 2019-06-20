import React from "react";
import classnames from 'classnames';
import styles from '../src/global-sass-files/_grid.scss';
import { storiesOf } from "@storybook/react";
import { IconInfo, InputField } from "../src";

storiesOf("Mixed Components", module).add(
  "Mixed Component - BAM",
  () => (
    <div className={classnames(styles["container__row"])}>
      <div className={classnames(styles["container__col-md-5"])}>
        <div className={classnames(styles["container__row"])}>
          <div className={classnames(styles["container__col-md-4"], styles["m-0"], styles["center-baseline"])}>
            <IconInfo iconColor="gray" iconName="question" iconText="Notification interval" />
          </div>
          <div className={classnames(styles["container__col-md-8"], styles["center-baseline"], styles["m-0"])}>
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