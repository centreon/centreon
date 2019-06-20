/* eslint-disable react/prop-types */
/* eslint-disable react/destructuring-assignment */
/* eslint-disable jsx-a11y/iframe-has-title */
/* eslint-disable react/no-unused-state */

import React, { Component } from 'react';
import { connect } from 'react-redux';
import Axios from '../Axios';

class DynamicComponentLoader extends Component {
  constructor(props) {
    super(props);

    this.state = {
      componentLoaded: false,
      componentExists: false,
    };
  }

  componentWillMount() {
    if (this.props.componentName) {
      document.addEventListener(
        `component${this.props.componentName}Loaded`,
        this.setComponentLoaded,
      );
      this.checkFileExists();
    }
  }

  componentWillReceiveProps(nextProps) {
    const { componentName } = nextProps;
    if (componentName !== this.props.componentName) {
      document.removeEventListener(
        `component${this.props.componentName}Loaded`,
        this.setComponentLoaded,
      );
      document.addEventListener(
        `component${componentName}Loaded`,
        this.setComponentLoaded,
      );
      this.checkFileExists();
    }
  }

  componentWillUnmount() {
    const { componentName } = this.props;
    document.removeEventListener(
      `component${componentName}Loaded`,
      this.setComponentLoaded,
    );
  }

  setComponentLoaded() {
    this.setState({
      componentLoaded: true,
    });
  }

  checkFileExists() {
    const { componentUrl, xhr } = this.props;
    xhr({
      requestType: 'GET',
      url: componentUrl,
      check: true,
    })
      .then(() => {
        this.setState({
          componentExists: true,
        });
      })
      .catch(() => {
        this.setState({
          componentExists: false,
        });
      });
  }

  render() {
    const { componentExists } = this.state;
    const { componentUrl } = this.props;

    return (
      <React.Fragment>
        {componentExists ? (
          <iframe
            src={componentUrl}
            style={{
              width: 0,
              height: 0,
              border: 'none',
            }}
          />
        ) : null}
      </React.Fragment>
    );
  }
}

const mapDispatchToProps = (dispatch) => ({
  xhr: (data) => {
    const { requestType } = data;
    return Axios(data, dispatch, requestType);
  },
});

export default connect(
  null,
  mapDispatchToProps,
)(DynamicComponentLoader);
