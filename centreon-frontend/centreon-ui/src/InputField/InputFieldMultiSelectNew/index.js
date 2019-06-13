import React from "react";
import classnames from 'classnames';
import styles from './input-field-multi-select.scss';

const InputFieldMultiSelect = ({
  type,
  placeholder,
  name,
  error,
  ...rest
}) => {
  return (
    <div className={classnames(styles["multi-select"])}>
      <input
        name={name}
        type={type}
        placeholder={placeholder}
        className={classnames(styles["multi-select-input"])}
        {...rest}
      />
      {error ? (
        <div className={classnames(styles["form-error"])}>
          {error}
        </div>
      ) : null}
    </div>
  );
};

export { InputFieldMultiSelect };

export default InputFieldMultiSelect;