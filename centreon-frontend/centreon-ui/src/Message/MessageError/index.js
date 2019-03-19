import React from "react";
import "./message-error.scss";

const MessageError = ({ messageError, text }) => {
  return <span class={`message-error ${messageError}`}>{text}</span>;
};

export default MessageError;