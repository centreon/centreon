/* eslint-disable react/prop-types */

import React, { Component } from 'react';
import classnames from 'classnames';
import Select from 'react-select';
import styles from './input-field-select.scss';

const options = [
  { value: 'remote1', label: 'Remote Server 1' },
  { value: 'remote2', label: 'Remote Server 2' },
  { value: 'remote3', label: 'Remote Server 3' },
];

class InputFieldSelect extends Component {
  constructor(props) {
    super(props);
    this.state = {
      selectedOption: null,
    };
  }

  handleChange(selectedOption) {
    this.setState({ selectedOption });
    // eslint-disable-next-line
    console.log(`Option selected:`, selectedOption);
  }

  render() {
    const { customClass } = this.props;
    const { selectedOption } = this.state;
    return (
      <Select
        className={classnames(
          styles['select-option'],
          styles[customClass || ''],
        )}
        value={selectedOption}
        onChange={this.handleChange}
        options={options}
        isMulti
        placeholder="Search here"
      />
    );
  }
}

export default InputFieldSelect;
