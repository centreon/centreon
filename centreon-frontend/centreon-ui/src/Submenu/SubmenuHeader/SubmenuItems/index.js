import React, { Component } from "react";

class SubmenuItems extends Component {
  render() {
    const { children } = this.props;
    return <ul className="submenu-items">{children}</ul>;
  }
}

export default SubmenuItems;
