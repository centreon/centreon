import React from "react";
import { storiesOf } from "@storybook/react";
import { Card, Button, ButtonAction, Title, Subtitle, IconInfo } from "../src";

storiesOf("Card", module).add("Card - with content", () => (
  <Card
    itemBorderColor="orange"
    itemFooterColor="orange"
    itemFooterLabel="Some label for the footer"
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
  </Card>
));

storiesOf("Card", module).add("Card - without content", () => (
  <Card
    itemBorderColor="orange"
    itemFooterColor="orange"
    itemFooterLabel="Some label for the footer"
  />
));
