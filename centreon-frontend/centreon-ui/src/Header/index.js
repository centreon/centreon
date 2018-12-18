import React, {Component} from 'react';
import './header.scss';

class Header extends Component {
  render() {
    const {children} = this.props;
    return (
      <header className="header">
        <div className="header-inner">
          {children}
        </div>
      </header>
    );
  }
}

export default Header;