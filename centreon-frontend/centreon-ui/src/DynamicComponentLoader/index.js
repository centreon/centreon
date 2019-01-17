import React, { Component } from 'react';

class DynamicComponentLoader extends Component {

    state = {
        componentLoaded:false
    }

    componentWillReceiveProps = (nextProps) => {
        const {componentName} = nextProps;
        if(componentName != this.props.componentName){
          document.removeEventListener(`component${this.props.componentName}Loaded`, this.setComponentLoaded);
          document.addEventListener(`component${componentName}Loaded`, this.setComponentLoaded)
        }
    }

    componentWillMount = () => {
        if (this.props.componentName) {
            document.addEventListener(`component${this.props.componentName}Loaded`, this.setComponentLoaded)
        }
    }

    setComponentLoaded = () => {
        this.setState({
            componentLoaded: true
        })
    }

    componentWillUnmount = () => {
        const { componentName } = this.props;
        document.removeEventListener(`component${componentName}Loaded`, this.setComponentLoaded);
    }

    render() {
        const { componentLoaded } = this.state;
        const { componentUrl } = this.props;

        return (
            <React.Fragment>
                {
                    componentLoaded && !(!!window.MSInputMethodContext && !!document.documentMode) ? null :
                    <iframe
                        src={componentUrl}
                        style={
                            {
                                width: 0,
                                height: 0,
                                border: '0',
                                border: 'none'
                            }
                        } />
                }
            </React.Fragment>
        );
    }
}

export default DynamicComponentLoader;