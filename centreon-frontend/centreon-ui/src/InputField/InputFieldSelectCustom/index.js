import React, { Component } from 'react';
import classnames from 'classnames';
import styles from './input-field.scss';
import IconToggleSubmenu from '../../Icon/IconToggleSubmenu';

class InputFieldSelectCustom extends Component {

  state = {
    active: false,
    selected: {}
  }

  toggleSelect = () => {
    const { active } = this.state;
    this.setState({
      active: !active
    })
  }

  optionChecked = (option) => {
    this.setState({
      selectedOption: option
    })
  }

  render() {
    const { active, selected } = this.state;
    const { size, label, error, options } = this.props;
    return (
      <div className={classnames(styles["input-select"], styles[size ? size : ''], styles[active ? "active" : ''], error ? styles['has-danger'] : '')}>
        <div className={classnames(styles["input-select-wrap"])}>
          <span className={classnames(styles["input-select-field"])}>{selected.name}</span>
          <IconToggleSubmenu iconPosition="icons-toggle-position-multiselect" iconType="arrow" onClick={this.toggleSelect.bind(this)} />
        </div>
        {
          active ?
            <div className={classnames(styles["input-select-dropdown"])}>
              {
                options ? options.map((option) => (
                  <span onClick={this.optionChecked.bind(this, option)} className={classnames(styles["input-select-label"])}>{option.name}</span>
                )) : null
              }
            </div>
            : null
        }

        {error ? (
          <div className={classnames(styles["form-error"])}>
            {error}
          </div>
        ) : null}
      </div>
    );
  }
}

export default InputFieldSelectCustom;