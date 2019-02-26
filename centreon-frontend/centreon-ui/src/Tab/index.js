import React, { Component } from "react";
import Tabs from './Tabs'
import "./tab.scss";

class Tab extends Component {
  render() {
    const {children} = this.props;
    return (
      <Tabs>
        {children}
      </Tabs>
    );
  }
}

export default Tab;
