import React from "react";
import { storiesOf } from "@storybook/react";
import { Panels, Wrapper, Header, Sidebar } from "../src";
import classnames from 'classnames';
import styles2 from '../src/Popup/PopupNew/popup.scss';
import ButtonCustom from "@material-ui/core/Button";
import IconCloseNew from "../src/MaterialComponents/Icons/IconClose";
import mock from "../src/Sidebar/mock2";
import reactMock from "../src/Sidebar/reactRoutesMock";
import SubmenuHeader from "../src/Submenu/SubmenuHeader/Mocked-Submenu";
import PopupNew from "../src/Popup/PopupNew"

storiesOf("Panels", module).add(
  "Panels",
  () => <Panels panelTtype="small" togglePanel={true} />,
  {
    notes: "A very simple component"
  }
);

storiesOf("Panels", module).add(
  "Panels - with header and menu",
  () => (
    <React.Fragment>
      <Wrapper style={{ alignItems: "stretch", display: "flex", padding: 0 }}>
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
          style={{ height: "100vh" }}
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
          <Panels panelTtype="small" togglePanel={true} />
        </div>
        <PopupNew popupType="small">
          <div className={classnames(styles2["popup-header"])}>
            <h3 className={classnames(styles2["popup-title"])}>
              Changes have been made
            </h3>
          </div>
          <div className={classnames(styles2["popup-body"])}>
            <p className={classnames(styles2["popup-info"])}>
              Would you like to save before closing?
            </p>
            <ButtonCustom
              variant="contained"
              color="primary"
              style={{
                backgroundColor: "#0072CE",
                fontSize: 11,
                textAlign: "center",
                border: "1px solid #0072CE"
              }}
            >
              SAVE
            </ButtonCustom>
            <ButtonCustom
              variant="contained"
              color="primary"
              style={{
                backgroundColor: "#0072CE",
                fontSize: 11,
                textAlign: "center",
                marginLeft: 30,
                backgroundColor: "transparent",
                color: "#0072CE",
                border: "1px solid #0072CE",
                boxSizing: "border-box"
              }}
            >
              DON'T SAVE
            </ButtonCustom>
          </div>
          <IconCloseNew />
        </PopupNew>
      </Wrapper>
    </React.Fragment>
  ),
  { notes: "A very simple component" }
);
