import React from "react";
import { storiesOf } from "@storybook/react";
import { Sidebar } from "../src";
import mock from "../src/Sidebar/mock2";
import reactMock from "../src/Sidebar/reactRoutesMock";

storiesOf("Sidebar", module).add(
  "Sidebar",
  () => (
    <Sidebar
      navigationData={mock}
      reactRoutes={reactMock}
      onNavigate={(id, url) => {
        console.log(id, url);
      }}
      handleDirectClick={(id, url) => {
        console.log(id, url);
      }}
    />
  ),
  { notes: "A very simple component" }
);
