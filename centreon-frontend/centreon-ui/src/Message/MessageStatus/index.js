import React from 'react';
import './message-status.scss';

const ContentMessage = ({messageStatus, messageText, messageInfo}) => {
  return (
    <span className={`message-status ${messageStatus}`}>
      {messageText}
      <span className={`message-status-info`}>{messageInfo}</span>
    </span>
  )
}

export default ContentMessage;