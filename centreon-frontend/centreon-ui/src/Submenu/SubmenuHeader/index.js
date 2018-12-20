import React, {Component} from 'react';
import './submenu.scss';

class SubmenuHeader extends Component {
  render() {
    const {submenuType, children} = this.props;
    return (
      <div className={`submenu-${submenuType}`}>
        {children}
      </div>
    );
  }
}

export default SubmenuHeader;