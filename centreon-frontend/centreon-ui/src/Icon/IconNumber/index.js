import React from "react";
import classnames from "classnames";
import styles from "./icon-number.scss";

const IconNumber = ({ iconColor, iconType, iconNumber }) => {
  return (
    <span
      className={classnames(
        styles.icons,
        styles["icons-number"],
        styles[iconType],
        styles[iconColor],
        styles["number-wrap"]
      )}
    >
      <span className={classnames(styles["number-count"])}>{iconNumber}</span>
    </span>
  );
};

export default IconNumber;
