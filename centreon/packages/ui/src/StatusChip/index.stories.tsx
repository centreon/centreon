import React from 'react';

import StatusChip, { SeverityCode } from '.';

export default { title: 'StatusChip' };

export const withNoneStatusCode = (): JSX.Element => (
  <StatusChip label="1" severityCode={SeverityCode.None} />
);

export const withUpOrOkStatusCode = (): JSX.Element => (
  <StatusChip label="Up" severityCode={SeverityCode.None} />
);

export const withWarningStatusCode = (): JSX.Element => (
  <StatusChip label="Warning" severityCode={SeverityCode.Medium} />
);

export const withDownOrCriticalStatusCode = (): JSX.Element => (
  <StatusChip label="Down" severityCode={SeverityCode.High} />
);

export const withUnreachableOrUnknownStatusCode = (): JSX.Element => (
  <StatusChip label="Unknown" severityCode={SeverityCode.Medium} />
);

export const withPendingStatusCode = (): JSX.Element => (
  <StatusChip label="Pending" severityCode={SeverityCode.Pending} />
);

export const withDownOrCriticalStatusCodeAndWithoutLabel = (): JSX.Element => (
  <StatusChip severityCode={SeverityCode.High} />
);

export const withUpOrOkStatusCodeAndWithoutLabel = (): JSX.Element => (
  <StatusChip severityCode={SeverityCode.None} />
);
