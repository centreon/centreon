import React, {Component} from 'react';
import Checkbox from '../../Checkbox';
import ScrollBar from '../../ScrollBar';
import IconToggleSubmenu from '../../Icon/IconToggleSubmenu';
import './input-multi-select.scss';

class InputFieldMultiSelect extends Component {
  render() {
    const {size, active, error} = this.props;
    return (
      <div className={`multi-select ${size ? size : ''} ${active
        ? active
        : ''}`  + (error ? ' has-danger' : '')}>
        <div className="multi-select-wrap">
          <input className="multi-select-input" type="text" placeholder="Search" />
          <IconToggleSubmenu iconType="arrow" />
        </div>
        <ScrollBar>
          <div className="multi-select-dropdown">
            <Checkbox label="Test" name="test" id='test' iconColor="green" checked={true} />
            <Checkbox label="Test 2" name="test2" id='test2' iconColor="green" checked={true} />
            <Checkbox label="Test 3" name="test3" id='test3' iconColor="green"/>
            <Checkbox label="Test 4" name="test4" id='test4' iconColor="green"/>
          </div>
        </ScrollBar>
        {error ? (
          <div class="form-error">
            {error}
          </div>
        ) : null}
      </div>
    );
  }
}

export default InputFieldMultiSelect;