import React from "react";
import classnames from "classnames";
import styles from "./message-error.scss";

const MessageError = ({ messageError, text, messageErrorPosition }) => {
  return (
    <span
      className={classnames(
        styles["message-error"],
        styles[messageError ? messageError : ""],
        styles[messageErrorPosition ? messageErrorPosition : ""]
      )}
    >
      {text}
    </span>
  );
};

export default MessageError;
