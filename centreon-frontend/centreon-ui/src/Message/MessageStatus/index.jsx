/* eslint-disable react/prop-types */

import React from 'react';
import classnames from 'classnames';
import styles from './message-status.scss';

function ContentMessage({ messageStatus, messageText, messageInfo }) {
  return (
    <span
      className={classnames(
        styles['message-status'],
        styles[messageStatus || ''],
      )}
    >
      {messageText}
      <span className={classnames(styles['message-status-info'])}>
        {messageInfo}
      </span>
    </span>
  );
}

export default ContentMessage;
