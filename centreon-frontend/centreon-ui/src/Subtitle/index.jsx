/* eslint-disable react/prop-types */

import React from 'react';
import classnames from 'classnames';
import styles from './custom-subtitles.scss';

function Subtitle({ label, subtitleType, customSubtitleStyles }) {
  const cn = classnames(
    styles['custom-subtitle'],
    styles[subtitleType],
    styles[customSubtitleStyles || ''],
  );
  return <span className={cn}>{label}</span>;
}

export default Subtitle;
