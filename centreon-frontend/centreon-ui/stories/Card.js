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
    >
      <IconInfo iconName="state" />
      <div className="custom-title-heading">
        <Title icon="object" label="Test Title" />
        <Subtitle label="Test Subtitle" />
      </div>
      <Button
        buttonType="regular"
        color="orange"
        label="Button example"
        iconActionType="update"
      />
      <ButtonAction buttonActionType="delete" buttonIconType="delete" />
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
