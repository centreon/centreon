import React from "react";
import { storiesOf } from "@storybook/react";
import { Sidebar } from "../src";
import mock from "../src/Sidebar/mock2";
import reactMock from "../src/Sidebar/reactRoutesMock";

function replaceQueryParam(param, newval, search) {
  var regex = new RegExp("([?;&])" + param + "[^&;]*[;&]?");
  var query = search.replace(regex, "$1").replace(/&$/, '');

  return (query.length > 2 ? query + "&" : "?") + (newval ? param + "=" + newval : '');
}

storiesOf("Sidebar", module).add(
  "Sidebar",
  () => (
    <Sidebar
      navigationData={mock}
      reactRoutes={reactMock}
      onNavigate={(id, url) => {
        window.location = '/iframe.html' + replaceQueryParam('p', id, window.location.search)
      }}
      handleDirectClick={(id, url) => {
        console.log(id, url);
      }}
    />
  ),
  { notes: "A very simple component" }
);
