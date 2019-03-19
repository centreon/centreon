import React, {Component} from 'react';
import Select from 'react-select';
import './input-field-select.scss';

const options = [
  { value: 'remote1', label: 'Remote Server 1' },
  { value: 'remote2', label: 'Remote Server 2' },
  { value: 'remote3', label: 'Remote Server 3' }
];

class InputFieldSelect extends Component {
  state = {
    selectedOption: null,
  }
  handleChange = (selectedOption) => {
    this.setState({ selectedOption });
    console.log(`Option selected:`, selectedOption);
  }
  render() { 
    const {customClass} = this.props;
    const { selectedOption } = this.state;
    return (
      <Select
        className={`select-option ${customClass ? customClass : ''}`}
        value={selectedOption}
        onChange={this.handleChange}
        options={options}
        isMulti={true}
        placeholder="Search here"
      />
    );
  }
}

export default InputFieldSelect;
 