import React, {Component} from 'react';
import classnames from 'classnames';
import styles from './sidebar.scss';
import Logo from '../Logo';
import LogoMini from '../Logo/LogoMini';
import Navigation from '../Navigation';
import mock from './navigationMock';

class Sidebar extends Component {
  
  state = {
    active: false
  };

  toggleNavigation = () => {
    const { active } = this.state;
    this.setState({
      active: !active
    });
  };

  render() { 
    const {active} = this.state;
    return ( 
      <nav className={classnames(styles["sidebar"], styles[active ? "active" : "mini"])} id="sidebar">
        <div className={classnames(styles["sidebar-inner"])}>
          {active ? 
            <Logo onClick={this.toggleNavigation} /> : 
            <LogoMini onClick={this.toggleNavigation} />
          }
          <Navigation customStyle={active ? "menu-big" : 'menu-small'} navigationData={mock}/>
          <div className={classnames(styles["sidebar-toggle-wrap"])} onClick={this.toggleNavigation} >
            <span className={classnames(styles["sidebar-toggle-icon"])}></span>
          </div>
        </div>
      </nav>
    );
  }
}
 
export default Sidebar;