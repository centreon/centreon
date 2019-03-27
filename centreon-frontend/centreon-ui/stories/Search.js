import React from "react";
import { storiesOf } from "@storybook/react";
import { SearchLive, SearchWithArrow } from "../src";

storiesOf("Search", module).add(
  "Search - live",
  () => <SearchLive label="name" />,
  { notes: "A very simple component" }
);

storiesOf("Search", module).add(
  "Search - with arrow",
  () => <SearchWithArrow searchLiveCustom="search-live-custom" />,
  { notes: "A very simple component" }
);

