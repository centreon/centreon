import { equals } from 'ramda';

export const isGenericText = equals<string | undefined>('/widgets/generictext');
