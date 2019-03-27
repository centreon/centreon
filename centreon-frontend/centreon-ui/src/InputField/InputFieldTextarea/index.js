import React from 'react';
import classnames from 'classnames';
import styles from './textarea.scss';
import IconInfo from '../../Icon/IconInfo';

const InputFieldTextarea = ({error, label, textareaType, iconName, iconColor}) => {
  return (
    <div 
      className={classnames(styles["form-group"], styles.textarea, styles[textareaType ? textareaType : ''], error ? styles['has-danger'] : '')}>
      {label && <label>{iconName ? <IconInfo iconName={iconName} iconColor={iconColor} /> : null } {label} </label>}
      <textarea className={classnames(styles["form-control"])} rows="3" />
      {error ? (
        <div className={classnames("form-error")}>
          {error}
        </div>
      ) : null}
    </div>
  );
};

export default InputFieldTextarea;