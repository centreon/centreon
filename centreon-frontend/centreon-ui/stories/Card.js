import React from "react";
import { storiesOf } from "@storybook/react";
import {
  Card,
  Button,
  ButtonAction,
  Title,
  Subtitle,
  IconInfo,
  CardItem
} from "../src";

storiesOf("Card", module).add("Card - with content", () => (
  <Card>
    <CardItem
      itemBorderColor="orange"
      itemFooterColor="orange"
      itemFooterLabel="Some label for the footer"
      style={{
        width: "250px"
      }}
      onClick={() => {
        alert("Card clicked- open popin");
      }}
    >
      <IconInfo iconName="state green" />
      <div className="custom-title-heading">
        <Title
          icon="object"
          label="Test Title"
          onClick={() => {
            alert("Card clicked- open popin");
          }}
        />
        <Subtitle
          label="Test Subtitle"
          onClick={() => {
            alert("Card clicked- open popin");
          }}
       />
      </div>
      <Button
        buttonType="regular"
        color="orange"
        label="Button example"
        iconActionType="update"
        iconColor='white'
        onClick={() => {
          alert("Button clicked");
        }}
      />
      <ButtonAction
        iconColor='gray'
        buttonActionType="delete"
        buttonIconType="delete"
        onClick={() => {
          alert("Button delete clicked");
        }}
      />
    </CardItem>
  </Card>
));

storiesOf("Card", module).add("Card - without content", () => (
  <Card>
    <CardItem
      itemBorderColor="orange"
      itemFooterColor="orange"
      itemFooterLabel="Some label for the footer"
      style={{
        width: "250px"
      }}
    />
  </Card>
));
