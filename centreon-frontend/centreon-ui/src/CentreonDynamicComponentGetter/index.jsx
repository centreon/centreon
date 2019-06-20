import React, { Component } from 'react';
import axios from 'axios';
import PropTypes from 'prop-types';
import DynamicComponentBundle from '../DynamicComponentBundle';

class CentreonDynamicComponentGetter extends Component {
  constructor(props) {
    super(props);
    this.state = {
      topologyUrl: '',
      componentName: false,
    };
  }

  componentWillMount() {
    const { topologyInfoApiUrl } = this.props;

    axios.get(topologyInfoApiUrl).then((response) => {
      this.setState({
        topologyUrl: response.data.topology_url,
        componentName: response.data.topology_name,
      });
    });
  }

  render() {
    const { topologyUrl, componentName } = this.state;

    return (
      <React.Fragment>
        <DynamicComponentBundle
          componentName={componentName}
          topologyUrl={topologyUrl}
        />
      </React.Fragment>
    );
  }
}

CentreonDynamicComponentGetter.propTypes = {
  topologyInfoApiUrl: PropTypes.string.isRequired,
};

export default CentreonDynamicComponentGetter;
