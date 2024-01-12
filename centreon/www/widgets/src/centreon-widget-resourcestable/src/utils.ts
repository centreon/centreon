import { T, always, cond, equals } from 'ramda';

export const formatStatusFilter = cond([
  [equals('success'), always(['ok', 'up'])],
  [equals('warning'), always(['warning'])],
  [equals('malfunction'), always(['down', 'critical'])],
  [equals('undefined'), always(['unreachable', 'unknown'])],
  [equals('pending'), always(['pending'])],
  [T, always([])]
]);
