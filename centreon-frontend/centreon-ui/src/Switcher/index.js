import React from "react";
import "./switcher.scss";

class Switcher extends React.Component{

  state = {
    value:true
  }

  UNSAFE_componentDidMount = () => {
    const {value} = this.props;
    if(value){
      this.setState({
        value
      })
    }
  }

  UNSAFE_componentWillReceiveProps = (nextProps) =>{
    const {value} = nextProps;
    if(this.state.value != value){
      this.setState({
        value
      })
    }
  }

  onChange = () => {
    const {onChange, filterKey} = this.props;
    const {value} = this.state;
    this.setState({
      value:!value
    });
    if(onChange){
      onChange(!value,filterKey);
    }
  }

  render(){
    const { switcherTitle, switcherStatus, customClass } = this.props;
    const { value } = this.state;
    return (
      <div className={`switcher ${customClass}`}>
      <span className="switcher-title">{switcherTitle ? switcherTitle : " "}</span>
      <span className="switcher-status">{switcherStatus}</span>
      <label className="switch">
        <input type="checkbox" checked={value} onClick={this.onChange.bind(this)}/>
        <span className="switch-slider switch-round" />
      </label>
    </div>
    )
  }
}

export default Switcher;
