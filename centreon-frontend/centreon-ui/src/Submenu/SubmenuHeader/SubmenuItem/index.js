import React, {Component} from 'react';

class SubmenuItem extends Component {
  render() {
    const {submenuLink, dotColored, submenuTitle, submenuCount} = this.props;
    return (
      <li className="submenu-item">
        <a className="submenu-item-link" href={submenuLink}>
          <span className="submenu-item-title">
            <span className={`submenu-item-dot dot-${dotColored}`}></span>
            {submenuTitle}
          </span>
          <span className="submenu-item-count">{submenuCount}</span>
        </a>
      </li>
    );
  }
}

export default SubmenuItem;