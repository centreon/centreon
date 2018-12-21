import React from "react";
import { storiesOf } from "@storybook/react";
import { Button, ButtonAction } from "../src";

storiesOf("Button", module).add(
  "Button - regular",
  () => (
    <React.Fragment>
      <Button
        label={"Button Regular"}
        buttonType="regular"
        color="orange"
        onClick={() => {}}
      />
      <Button
        label={"Button Regular"}
        buttonType="regular"
        color="blue"
        onClick={() => {}}
      />
      <Button
        label={"Button Regular"}
        buttonType="regular"
        color="green"
        onClick={() => {}}
      />
      <Button
        label={"Button Regular"}
        buttonType="regular"
        color="red"
        onClick={() => {}}
      />
      <Button
        label={"Button Regular"}
        buttonType="regular"
        color="gray"
        onClick={() => {}}
      />
    </React.Fragment>
  ),
  { notes: "A very simple component" }
);

storiesOf("Button", module).add("Button - bordered", () => (
  <React.Fragment>
    <Button
      label="Button Bordered"
      buttonType="bordered"
      color="orange"
      onClick={() => {
        alert("Hey");
      }}
    />
    <Button
      label="Button Bordered"
      buttonType="bordered"
      color="blue"
      onClick={() => {
        alert("Hey");
      }}
    />
    <Button
      label="Button Bordered"
      buttonType="bordered"
      color="green"
      onClick={() => {
        alert("Hey");
      }}
    />
    <Button
      label="Button Bordered"
      buttonType="bordered"
      color="red"
      onClick={() => {
        alert("Hey");
      }}
    />
    <Button
      label="Button Bordered"
      buttonType="bordered"
      color="gray"
      onClick={() => {
        alert("Hey");
      }}
    />
    <Button
      label="Button Bordered"
      buttonType="bordered"
      color="black"
      onClick={() => {
        alert("Hey");
      }}
    />
  </React.Fragment>
));

storiesOf("Button", module).add("Button - with icon", () => (
  <Button
    label="Button with icon"
    buttonType="regular"
    color="orange"
    iconActionType="update"
  />
));

storiesOf("Button", module).add("Button - action", () => (
  <ButtonAction buttonActionType="delete" buttonIconType="delete" />
));
