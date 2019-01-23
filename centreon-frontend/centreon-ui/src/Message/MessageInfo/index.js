import React from 'react';
import './message-info.scss';

const MessageInfo = ({messageInfo,text}) => {
  return <span class={`message-info ${messageInfo}`}>{text}</span>
}

export default MessageInfo;
