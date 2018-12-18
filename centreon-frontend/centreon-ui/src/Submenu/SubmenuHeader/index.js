import React, {Component} from 'react';
import './submenu.scss';

class SubmenuHeader extends Component {
  render() {
    const {submenuType, children} = this.props;
    return (
      <div class={`submenu-${submenuType}`}>
        {children}
      </div>
    );
  }
}

export default SubmenuHeader;