import React, { Component } from "react";
import DynamicComponentPosition from "../DynamicComponentPosition";
import DynamicComponentLoader from "../DynamicComponentLoader";

class DynamicComponentBundle extends Component {
  render() {
    const { topologyUrl, componentName } = this.props;

    return (
      <React.Fragment>
        <DynamicComponentPosition componentName={componentName} />
        <DynamicComponentLoader
          componentName={componentName}
          componentUrl={topologyUrl}
        />
      </React.Fragment>
    );
  }
}

export default DynamicComponentBundle;
