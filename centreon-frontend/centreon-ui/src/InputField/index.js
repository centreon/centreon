import React from "react";
import './input-text.scss';
import IconInfo from '../Icon/IconInfo';

const InputField = ({
  type,
  label,
  placeholder,
  topRightLabel,
  name,
  inputSize,
  error,
  iconName,
  iconColor,
  ...rest
}) => {
  return (
    <div className={`form-group ${inputSize}` + (error ? ' has-danger' : '')}>
      <label htmlFor={rest.id}>
        <span>{iconName ? <IconInfo iconName={iconName} iconColor={iconColor}/> : null } {label}</span>
        <span className="label-option required">
          {topRightLabel ? topRightLabel : null}
        </span>
      </label>
      <input
        name={name}
        type={type}
        placeholder={placeholder}
        className="form-control"
      />
      {error ? (
        <div class="form-error">
          {error}
        </div>
      ) : null}
    </div>
  );
};

export { InputField };

export default InputField;