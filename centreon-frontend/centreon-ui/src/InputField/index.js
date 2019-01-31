import React from "react";
import './input-text.scss';

const InputField = ({
  type,
  label,
  placeholder,
  topRightLabel,
  name,
  ...rest
}) => {
  return (
    <div className="form-group" style={{width: '200px'}}>
      <label htmlFor={rest.id}>
        <span>{label}</span>
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
    </div>
  );
};

export { InputField };

export default InputField;
