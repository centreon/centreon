import React, {Component} from "react";
import "./search-live.scss";

class SearchLive extends Component {
  onChange = e => {
    const { onChange, filterKey } = this.props;
    onChange(e.target.value, filterKey);
  };

  render() {
    const { label, value } = this.props;
    return (
      <div className="search-live">
        {label && <label>{label}</label>}
        <input type="text" value={value} onChange={this.onChange.bind(this)} />
      </div>
    );
  }
}

export default SearchLive;