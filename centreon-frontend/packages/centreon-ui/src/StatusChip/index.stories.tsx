import React from 'react';

import StatusChip, { SeverityCode } from '.';

export default { title: 'StatusChip' };

export const withOkSeverityCode = (): JSX.Element => (
  <StatusChip label="Up" severityCode={SeverityCode.Ok} />
);

export const withMediumSeverityCode = (): JSX.Element => (
  <StatusChip label="Warning" severityCode={SeverityCode.Medium} />
);

export const withHighSeverityCode = (): JSX.Element => (
  <StatusChip label="Down" severityCode={SeverityCode.High} />
);

export const withNoneSeverityCode = (): JSX.Element => (
  <StatusChip label="Unknown" severityCode={SeverityCode.None} />
);

export const withPendingSeverityCode = (): JSX.Element => (
  <StatusChip label="Pending" severityCode={SeverityCode.Pending} />
);

export const withHighSeverityCodeAndWithoutLabel = (): JSX.Element => (
  <StatusChip severityCode={SeverityCode.High} />
);

export const withNoneSeverityCodeAndWithoutLabel = (): JSX.Element => (
  <StatusChip severityCode={SeverityCode.None} />
);
