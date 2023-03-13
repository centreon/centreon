import numeral from 'numeral';

export const formatTopCounterCount = (number: number | string): string =>
  numeral(number).format('0a');

export const formatTopCounterUnhandledOverTotal = (
  unhandled: number | string,
  total: number | string
): string =>
  `${formatTopCounterCount(unhandled)}/${formatTopCounterCount(total)}`;
