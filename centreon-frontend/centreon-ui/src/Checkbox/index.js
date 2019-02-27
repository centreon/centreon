import React from "react";
import './checkbox.scss';

const Checkbox = ({
  iconColor,
  checked,
  label,
  value,
  info,
  name,
  ...rest
}) => (
  <div className="form-group">
    <div className={`custom-control custom-checkbox ${iconColor ? iconColor : ''}`}>
      <input
        name={name}
        aria-checked={checked}
        checked={checked}
        className="custom-control-input"
        type="checkbox"
      />
      <label htmlFor={rest.id} className="custom-control-label">
        {label}
        {info}
      </label>
    </div>
  </div>
);

export { Checkbox };

export default Checkbox;
