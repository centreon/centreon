import React from "react";
import './radio-button.scss';

const RadioField = ({ checked, error, label, info, iconColor, ...rest }) => (
  <div class={`custom-control custom-radio form-group ${iconColor ? iconColor : ''}`}>
    <input
      className="form-check-input"
      type="radio"
      aria-checked={checked}
      checked={checked}
      info
    />
    <label htmlFor={rest.id} className="custom-control-label">
      {label}
      {info}
    </label>
    {error ? (
      <div className="invalid-feedback">
        <i className="fas fa-exclamation-triangle" />
        <div className="field__msg  field__msg--error">{error}</div>
      </div>
    ) : null}
  </div>
);

export { RadioField };

export default RadioField;