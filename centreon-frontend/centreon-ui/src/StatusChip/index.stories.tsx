import React from 'react';

import StatusChip, { StatusCode } from '.';

export default { title: 'StatusChip' };

export const withNoneStatusCode = (): JSX.Element => (
  <StatusChip label="1" statusCode={StatusCode.None} />
);

export const withUpOrOkStatusCode = (): JSX.Element => (
  <StatusChip label="Up" statusCode={StatusCode.UpOrOk} />
);

export const withDownOrWarningStatusCode = (): JSX.Element => (
  <StatusChip label="Down" statusCode={StatusCode.DownOrWarning} />
);

export const withUnreachableOrCriticalStatusCode = (): JSX.Element => (
  <StatusChip
    label="Unreachable"
    statusCode={StatusCode.UnreachableOrCritical}
  />
);

export const withUnknownStatusCode = (): JSX.Element => (
  <StatusChip label="Unknown" statusCode={StatusCode.Unknown} />
);

export const withPendingStatusCode = (): JSX.Element => (
  <StatusChip label="Pending" statusCode={StatusCode.Pending} />
);

export const withUnreachableOrCriticalStatusCodeAndWithoutLabel = (): JSX.Element => (
  <StatusChip statusCode={StatusCode.UnreachableOrCritical} />
);

export const withUpOrOkStatusCodeAndWithoutLabel = (): JSX.Element => (
  <StatusChip statusCode={StatusCode.UpOrOk} />
);
