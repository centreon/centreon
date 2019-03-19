import React, {Component} from "react";
import ButtonActionInput from '../../Button/ButtonActionInput';
import "./search-with-arrow.scss";

class SearchLive extends Component {
  onChange = e => {
    const { onChange, filterKey } = this.props;
    onChange(e.target.value, filterKey);
  };

  render() {
    const { label, value } = this.props;

    return (
      <div className="search-live custom">
        {label && <label>{label}</label>}
        <input type="text" value={value} onChange={this.onChange.bind(this)} />
        <ButtonActionInput
          buttonColor="green"
          iconColor="white"
          buttonActionType="delete"
          buttonIconType="arrow-right"
        />
      </div>
    );
  }
}

export default SearchLive;