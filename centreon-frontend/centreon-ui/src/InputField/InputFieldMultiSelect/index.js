import React, {Component} from 'react';
import classnames from 'classnames';
import styles from './input-multi-select.scss';
import Checkbox from '../../Checkbox';
import ScrollBar from '../../ScrollBar';
import IconToggleSubmenu from '../../Icon/IconToggleSubmenu';

class InputFieldMultiSelect extends Component {
  render() {
    const {size, active, error} = this.props;
    return (
      <div className={classnames(styles["multi-select"], styles[size ? size : ''], styles[active ? active : ''], error ? styles['has-danger'] : '')}>
        <div className={classnames(styles["multi-select-wrap"])}>
          <input className={classnames(styles["multi-select-input"])} type="text" placeholder="Search" />
          <IconToggleSubmenu iconPosition="icons-toggle-position-multiselect" iconType="arrow" />
        </div>
          <div className={classnames(styles["multi-select-dropdown"])}>
            <ScrollBar>
              <Checkbox label="Test" name="test" id='test' iconColor="green" checked={true} />
              <Checkbox label="Test 2" name="test2" id='test2' iconColor="green" checked={true} />
              <Checkbox label="Test 3" name="test3" id='test3' iconColor="green"/>
              <Checkbox label="Test 4" name="test4" id='test4' iconColor="green"/>
            </ScrollBar>
          </div>
        {error ? (
          <div className={classnames(styles["form-error"])}>
            {error}
          </div>
        ) : null}
      </div>
    );
  }
}

export default InputFieldMultiSelect;