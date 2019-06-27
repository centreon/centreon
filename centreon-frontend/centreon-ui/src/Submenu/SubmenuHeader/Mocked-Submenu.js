import React, { Component } from "react";
import classnames from "classnames";
import styles from "./submenu.scss";
import IconHeader from "../../Icon/IconHeader";
import IconToggleSubmenu from "../../Icon/IconToggleSubmenu";
import IconCustomDot from "../../Icon/IconCustomDot";
import IconNumber from "../../Icon/IconNumber";
import SubmenuItems from "../../Submenu/SubmenuHeader/SubmenuItems";
import SubmenuItem from "../../Submenu/SubmenuHeader/SubmenuItem";

class SubmenuHeader extends Component {
  state = {
    active: false
  };

  toggleSubmenu = () => {
    const { active } = this.state;

    this.setState({
      active: !active
    });
  };

  render() {
    const { submenuType, children, ...props } = this.props;
    const { active } = this.state;

    return (
      <div
        className={classnames(styles[`submenu-${submenuType}`], {
          [styles["submenu-active"]]: !!active
        })}
        {...props}
      >
        <div
          className={classnames(
            styles["submenu-top"],
            styles[ active ? "submenu-active" : ""]
          )}
          style={{
            width: "200px"
          }}
        >
          <IconHeader iconType="services" iconName="services" onClick={this.toggleSubmenu.bind(this)}>
            <IconCustomDot />
          </IconHeader>
          <IconNumber iconType="bordered" iconColor="red" iconNumber="3" />
          <IconNumber
            iconType="bordered"
            iconColor="gray-dark"
            iconNumber="5"
          />
          <IconNumber iconType="colored" iconColor="green" iconNumber="10" />
          <IconToggleSubmenu
            iconPosition="icons-toggle-position-right"
            iconType="arrow"
            onClick={this.toggleSubmenu.bind(this)}
          />
          <div className={classnames(styles["submenu-toggle"])}>
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
      </div>
    );
  }
}

export default SubmenuHeader;
