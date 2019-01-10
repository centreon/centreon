import React from "react";
import "./switcher.scss";

class Switcher extends React.Component{

  state = {
    value:false
  }

  UNSAFE_componentDidMount = () => {
    const {defaultValue} = this.props;
    if(defaultValue){
      this.setState({
        value:defaultValue
      })
    }
  }

  onChange = () => {
    const {onChange} = this.props;
    const {value} = this.state;
    this.setState({
      value:!value
    });
    if(onChange){
      onChange(!value);
    }
  }

  render(){
    const { switcherTitle, switcherStatus, customClass } = this.props;
    return (
      <div className={`switcher ${customClass}`}>
      <span className="switcher-title">{switcherTitle ? switcherTitle : " "}</span>
      <span className="switcher-status">{switcherStatus}</span>
      <label className="switch">
        <input type="checkbox" onClick={this.onChange.bind(this)}/>
        <span className="switch-slider switch-round" />
      </label>
    </div>
    )
  }
}

export default Switcher;
