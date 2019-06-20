/* eslint-disable react/prop-types */
/* eslint-disable react/jsx-no-target-blank */

import React from 'react';
import classnames from 'classnames';
import styles from './content-description.scss';

function DescriptionContent({ date, title, text, note, link }) {
  return (
    <React.Fragment>
      {date ? (
        <span className={classnames(styles['content-description-date'])}>
          {date}
        </span>
      ) : null}
      {title ? (
        <h3 className={classnames(styles['content-description-title'])}>
          {title}
        </h3>
      ) : null}
      {text ? (
        <p className={classnames(styles['content-description-text'])}>
          {text.split('\n').map((i) => {
            return (
              <span>
                {i}
                <br />
              </span>
            );
          })}
        </p>
      ) : null}
      {note ? (
        <span
          className={classnames(styles['content-description-release-note'])}
        >
          {link ? (
            <a
              className={classnames(styles['content-description-release-note'])}
              href={note}
              target="_blank"
            >
              {note}
            </a>
          ) : (
            note
          )}
        </span>
      ) : null}
    </React.Fragment>
  );
}

export default DescriptionContent;
