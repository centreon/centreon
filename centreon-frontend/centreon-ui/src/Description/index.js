/* eslint-disable react/jsx-no-target-blank */
/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */

import React from 'react';
import clsx from 'clsx';
import styles from './content-description.scss';

const DescriptionContent = ({ date, title, text, note, link }) => (
  <React.Fragment>
    {date ? (
      <span className={clsx(styles['content-description-date'])}>{date}</span>
    ) : null}
    {title ? (
      <h3 className={clsx(styles['content-description-title'])}>{title}</h3>
    ) : null}
    {text ? (
      <p className={clsx(styles['content-description-text'])}>
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
      <span className={clsx(styles['content-description-release-note'])}>
        {link ? (
          <a
            className={clsx(styles['content-description-release-note'])}
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

export default DescriptionContent;
