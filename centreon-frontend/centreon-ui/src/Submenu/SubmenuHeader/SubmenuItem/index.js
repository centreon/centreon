import React, { Component } from "react";

class SubmenuItem extends Component {
  render() {
    const { dotColored, submenuTitle, submenuCount } = this.props;
    return (
      <li className="submenu-item">
        <span className="submenu-item-title">
          <span className={`submenu-item-dot dot-${dotColored}`} />
          {submenuTitle}
        </span>
        <span className="submenu-item-count">{submenuCount}</span>
      </li>
    );
  }
}

export default SubmenuItem;
