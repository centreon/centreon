import React, { Component } from 'react';
import axios from "axios";

class DynamicComponentLoader extends Component {

  state = {
    is_react: null,
    comp: null,
    is_comp: false,
    reactUrl: ""
  }

  componentWillMount = () => {

    const { topologyApiUrl } = this.props;

    axios.get(topologyApiUrl).then((response) => {
      if (response.data.is_react == 0) {
        this.setState({
          is_react: false
        })
      } else {

        this.setState({
          is_react: true,
          is_comp: false,
          reactUrl: response.data.topology_url,
          componentName: response.data.topology_name
        },
          () => {

            document.addEventListener(`component${response.data.topology_name}Loaded`, (e) => {
              this.setState({
                is_react: true,
                is_comp: true
              })
            })

          })
      }
    })

  }

  render() {
    const { is_react, reactUrl, is_comp, componentName } = this.state;

    let Comp = componentName ? window[componentName] : <div></div>;

    return (
      <React.Fragment>
        {
          is_react ?
            (
              is_comp ? <Comp /> :
                <iframe
                  src={reactUrl}
                  style={
                    {
                      width: 0,
                      height: 0,
                      border: '0',
                      border: 'none'
                    }
                  } />
            )
            : null
        }
      </React.Fragment>
    );
  }
}

export default DynamicComponentLoader;