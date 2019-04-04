import React, {Component} from 'react';
import classnames from 'classnames';
import styles from './input-field.scss';
import ScrollBar from '../../ScrollBar';
import CustomIconWithText from '../../Custom/CustomIconWithText';
import IconToggleSubmenu from '../../Icon/IconToggleSubmenu';

class InputFieldSelectCustom extends Component {
  render() {
    const {size, active, label, error} = this.props;
    return (
      <div className={classnames(styles["input-select"], styles[size ? size : ''], styles[active ? active : ''], error ? styles['has-danger'] : '')}>
        <div className={classnames(styles["input-select-wrap"])}>
          <input className={classnames(styles["input-select-field"])} type="text" placeholder="Search" />
          <IconToggleSubmenu iconPosition="icons-toggle-position-multiselect" iconType="arrow" />
        </div>
          <div className={classnames(styles["input-select-dropdown"])}>
            <ScrollBar scrollBarCustom="scrollbar-container-custom">
              <CustomIconWithText label="Test 123213" />
              <span className={classnames(styles["input-select-label"])}>Test 1</span>
              <span className={classnames(styles["input-select-label"])}>Test 1</span>
              <span className={classnames(styles["input-select-label"])}>Test 1</span>
              <span className={classnames(styles["input-select-label"])}>Test 1</span>
              <span className={classnames(styles["input-select-label"])}>Test 1</span>
              <span className={classnames(styles["input-select-label"])}>Test 1</span>
              <span className={classnames(styles["input-select-label"])}>Test 1</span>
              <span className={classnames(styles["input-select-label"])}>Test 1</span>
              <span className={classnames(styles["input-select-label"])}>Test 1</span>
              <span className={classnames(styles["input-select-label"])}>Test 1</span>
              <span className={classnames(styles["input-select-label"])}>Test 1</span>
              <span className={classnames(styles["input-select-label"])}>Test 1</span>
              <span className={classnames(styles["input-select-label"])}>Test 1</span>
              <span className={classnames(styles["input-select-label"])}>Test 1</span>
              <span className={classnames(styles["input-select-label"])}>Test 1</span>
              <span className={classnames(styles["input-select-label"])}>Test 1</span>
              <span className={classnames(styles["input-select-label"])}>Test 1</span>
              <span className={classnames(styles["input-select-label"])}>Test 1</span>
              <span className={classnames(styles["input-select-label"])}>Test 1</span>
              <span className={classnames(styles["input-select-label"])}>Test 1</span>
              <span className={classnames(styles["input-select-label"])}>Test 1</span>
              <span className={classnames(styles["input-select-label"])}>Test 1</span>
              <span className={classnames(styles["input-select-label"])}>Test 1</span>
              <span className={classnames(styles["input-select-label"])}>Test 1</span>
              <span className={classnames(styles["input-select-label"])}>Test 1</span>
              <span className={classnames(styles["input-select-label"])}>Test 1</span>
              <span className={classnames(styles["input-select-label"])}>Test 1</span>
              <span className={classnames(styles["input-select-label"])}>Test 1</span>
              <span className={classnames(styles["input-select-label"])}>Test 1</span>
              <span className={classnames(styles["input-select-label"])}>Test 1</span>
              <span className={classnames(styles["input-select-label"])}>Test 1</span>
              <span className={classnames(styles["input-select-label"])}>Test 2</span>
              <span className={classnames(styles["input-select-label"])}>Test 3</span>
              <span className={classnames(styles["input-select-label"])}>Test 4</span>
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

export default InputFieldSelectCustom;