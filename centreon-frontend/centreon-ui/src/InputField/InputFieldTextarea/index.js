import React from 'react';
import './textarea.scss';
import IconInfo from '../../Icon/IconInfo';

const InputFieldTextarea = ({error, label, textareaType, iconName, iconColor}) => {
  return (
    <div className={`form-group textarea ${textareaType}` + (error ? ' has-danger' : '')}>
      <label>{iconName ? <IconInfo iconName={iconName} iconColor={iconColor} /> : null } {label} </label>
      <textarea className="form-control" rows="3" />
      {error ? (
        <div className="form-error">
          {error}
        </div>
      ) : null}
    </div>
  );
};

export default InputFieldTextarea;