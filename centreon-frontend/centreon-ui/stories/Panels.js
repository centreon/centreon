import React from "react";
import { storiesOf } from "@storybook/react";
import {
  Panels,
  Wrapper,
  Header,
  Sidebar
} from "../src";
import mock from "../src/Sidebar/mock2";
import reactMock from "../src/Sidebar/reactRoutesMock";
import SubmenuHeader from '../src/Submenu/SubmenuHeader/Mocked-Submenu';

storiesOf("Panels", module).add("Panels", () => <Panels panelTtype="small" togglePanel={true}/>, {
  notes: "A very simple component"
});

storiesOf("Panels", module).add(
  "Panels - with header and menu",
  () => (
    <React.Fragment>
      <Wrapper style={{ alignItems: "stretch", display: "flex", padding: 0}}>
        <Sidebar
          navigationData={mock}
          externalHistory={window}
          reactRoutes={reactMock}
          onNavigate={(id, url) => {
            window.location =
              "/iframe.hqtml" +
              replaceQueryParam("p", id, window.location.search);
          }}
          handleDirectClick={(id, url) => {
            console.log(id, url);
          }}
          style={{height: '100vh'}}
        />
        <div
          className="content"
          style={{ display: "flex", flexDirection: "column", width: "100%" }}
        >
          <Header style={{ height: "56px", width: "100%", marginBottom: 20 }}>
            <SubmenuHeader submenuType="header" />
            <SubmenuHeader submenuType="header" />
            <SubmenuHeader submenuType="header" />
          </Header>
          <Panels panelTtype="small" togglePanel={true}/>
        </div>
      </Wrapper>
    </React.Fragment>
  ),
  { notes: "A very simple component" }
);
