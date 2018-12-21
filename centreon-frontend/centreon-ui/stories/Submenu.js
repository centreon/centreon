import React from "react";
import { storiesOf } from "@storybook/react";
import {
  SubmenuHeader,
  IconHeader,
  IconNumber,
  SubmenuItems,
  SubmenuItem,
  IconToggleSubmenu
} from "../src";

storiesOf("Submenu", module).add("Submenu - toggled without items", () => (
  <SubmenuHeader submenuType="header">
    <div
      className="submenu-top submenu-active"
      style={{
        width: "220px",
        minHeight: "53px"
      }}
    >
      <div className="submenu-toggle" style={{ minHeight: "200px" }}>
        <SubmenuItems />
      </div>
    </div>
  </SubmenuHeader>
));

storiesOf("Submenu", module).add(
  "Submenu - toggled with top and bottom items",
  () => (
    <SubmenuHeader submenuType="header">
      <div
        className="submenu-top submenu-active"
        style={{
          width: "200px"
        }}
      >
        <IconHeader iconType="services" iconName="services" />
        <IconNumber iconType="bordered" iconColor="red" iconNumber="3" />
        <IconNumber iconType="bordered" iconColor="gray-dark" iconNumber="5" />
        <IconNumber iconType="colored" iconColor="green" iconNumber="10" />
        <IconToggleSubmenu iconType="arrow" />
        <div className="submenu-toggle">
          <SubmenuItems>
            <SubmenuItem submenuTitle="All" submenuCount="151" />
            <SubmenuItem
              submenuLink="http://google.com"
              dotColored="red"
              submenuTitle="Down"
              submenuCount="0/0"
            />
            <SubmenuItem
              dotColored="gray"
              submenuTitle="Unreachable"
              submenuCount="0/0"
            />
            <SubmenuItem
              dotColored="green"
              submenuTitle="Up"
              submenuCount="151"
            />
            <SubmenuItem
              dotColored="blue"
              submenuTitle="Pending"
              submenuCount="0"
            />
          </SubmenuItems>
        </div>
      </div>
    </SubmenuHeader>
  )
);

storiesOf("Submenu", module).add("Submenu - toggled with bottom items", () => (
  <SubmenuHeader submenuType="header">
    <div
      className="submenu-top submenu-active"
      style={{
        width: "200px",
        minHeight: "40px"
      }}
    >
      <div className="submenu-toggle">
        <SubmenuItems>
          <SubmenuItem submenuTitle="All" submenuCount="151" />
          <SubmenuItem
            submenuLink="http://google.com"
            dotColored="red"
            submenuTitle="Down"
            submenuCount="0/0"
          />
          <SubmenuItem
            dotColored="gray"
            submenuTitle="Unreachable"
            submenuCount="0/0"
          />
          <SubmenuItem
            dotColored="green"
            submenuTitle="Up"
            submenuCount="151"
          />
          <SubmenuItem
            dotColored="blue"
            submenuTitle="Pending"
            submenuCount="0"
          />
        </SubmenuItems>
      </div>
    </div>
  </SubmenuHeader>
));
