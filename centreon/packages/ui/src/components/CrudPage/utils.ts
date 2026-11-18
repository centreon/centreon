import { equals, type } from 'ramda';

export const isAFunction = (property: unknown): boolean =>
  equals('Function', type(property));
