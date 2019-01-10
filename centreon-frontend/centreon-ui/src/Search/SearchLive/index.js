import React from "react";
import "./search-live.scss";

class SearchLive extends React.Component {

  onChange = (e) => {
    const {onChange} = this.props;
    onChange(e.target.value);
  }

  render() {
    const { label } = this.props;
    
    return (
      <div className="search-live">
        <label>{label}</label>
        <input type="text" onChange={this.onChange.bind(this)} />
      </div>
    )
  }
}

export default SearchLive;
