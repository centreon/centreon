import React, { Component } from "react";
import Tabs from "./Tabs";

class Tab extends Component {
  render() {
    const { children, error } = this.props;
    return <Tabs error={error}>{children}</Tabs>;
  }
}

export default Tab;
