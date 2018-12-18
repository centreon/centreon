import React, {Component} from 'react';
import './header.scss';

class Header extends Component {
  render() {
    const {children, style} = this.props;
    return (
      <header className="header" style={style}>
        <div className="header-inner">
          {children}
        </div>
      </header>
    );
  }
}

export default Header;