/* eslint-disable react/prop-types */

import React from 'react';
import classnames from 'classnames';
import styles from './message-error.scss';

function MessageError({ messageError, text, messageErrorPosition }) {
  return (
    <span
      className={classnames(
        styles['message-error'],
        styles[messageError || ''],
        styles[messageErrorPosition || ''],
      )}
    >
      {text}
    </span>
  );
}

export default MessageError;
