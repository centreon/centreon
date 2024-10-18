import { equals, type } from 'ramda';

export const isAFunction = (property): boolean =>
  equals('Function', type(property));
