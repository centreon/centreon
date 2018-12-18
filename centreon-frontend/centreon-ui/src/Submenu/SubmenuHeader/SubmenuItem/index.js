import React, {Component} from 'react';

class SubmenuItem extends Component {
  render() {
    const {submenuLink, dotColored, submenuTitle, submenuCount} = this.props;
    return (
      <li class="submenu-item">
        <a class="submenu-item-link" href={submenuLink}>
          <span class="submenu-item-title">
            <span className={`submenu-item-dot dot-${dotColored}`}></span>
            {submenuTitle}
          </span>
          <span class="submenu-item-count">{submenuCount}</span>
        </a>
      </li>
    );
  }
}

export default SubmenuItem;