import React from 'react';
import classnames from 'classnames';
import styles from './textarea.scss';
import IconInfo from '../../Icon/IconInfo';

const InputFieldTextarea = ({
  error,
  label,
  textareaType,
  iconName,
  iconColor,
  ...rest
}) => {
  return (
    <div
      className={classnames(
        styles['form-group'],
        styles.textarea,
        styles[textareaType || ''],
        error ? styles['has-danger'] : '',
      )}
    >
      {label && (
        <label>
          {iconName ? (
            <IconInfo iconName={iconName} iconColor={iconColor} />
          ) : null}{' '}
          {label}{' '}
        </label>
      )}
      <textarea
        className={classnames(styles['form-control'])}
        rows="3"
        {...rest}
      />
      {error ? (
        <div className={classnames(styles['form-error'])}>{error}</div>
      ) : null}
    </div>
  );
};

export default InputFieldTextarea;
