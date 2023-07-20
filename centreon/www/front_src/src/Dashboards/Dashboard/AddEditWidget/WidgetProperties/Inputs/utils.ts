import { path } from 'ramda';

export const getProperty = <T>({ propertyName, obj }): T | undefined =>
  path<T>(['options', propertyName], obj);
