import React, { Component } from 'react';
import classnames from 'classnames';
import styles from './input-multi-select.scss';
import Checkbox from '../../Checkbox';
import IconToggleSubmenu from '../../Icon/IconToggleSubmenu';

class InputFieldMultiSelect extends Component {

  state = {
    active: false,
    allOptions: [],
    options: [],
    activeOptions: {}
  }

  componentWillReceiveProps = (nextProps) => {
    const { options } = nextProps;
    const {initialized} = this.state;
    if(options && !initialized){
      this.setState({
        initialized:true,
        options,
        allOptions: options
      })
    }
  }

  componentWillMount = () => {
    const { options } = this.props;
    if(options){
      this.setState({
        initialized:true,
        options,
        allOptions: options
      })
    }
  }

  searchTextChanged = (e) => {
    let searchString = e.target.value;
    let { allOptions } = this.state;
    this.setState({
      options: allOptions.filter(option => {
        return option.name.indexOf(searchString) > -1
      })
    })
  }

  toggleSelect = () => {
    const { active } = this.state;
    this.setState({
      active: !active
    })
  }

  optionChecked = (option) => {
    let { activeOptions } = this.state;
    activeOptions[option.id] = activeOptions[option.id] ? false : true;
    this.setState({
      activeOptions
    })
  }

  render() {
    const { active, options, activeOptions } = this.state;
    const { size, error } = this.props;
    return (
      <div className={classnames(styles["multi-select"], styles[size ? size : ''], styles[active ? 'active' : ''], error ? styles['has-danger'] : '')}>
        <div className={classnames(styles["multi-select-wrap"])}>
          <input onChange={this.searchTextChanged} className={classnames(styles["multi-select-input"])} type="text" placeholder="Search" />
          <IconToggleSubmenu iconPosition="icons-toggle-position-multiselect" iconType="arrow" onClick={this.toggleSelect.bind(this)} />
        </div>
        {
          active ?
            <div className={classnames(styles["multi-select-dropdown"])}>
              {
                options ?
                options.map((option, index) => (
                  <Checkbox 
                  key={`multiselect-checkbox-${index}`} 
                  label={option.name} 
                  onClick={this.optionChecked.bind(this, option)} 
                  iconColor="green" 
                  onChange={()=>{}}
                  checked={activeOptions[option.id] || false} />
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

export default InputFieldMultiSelect;