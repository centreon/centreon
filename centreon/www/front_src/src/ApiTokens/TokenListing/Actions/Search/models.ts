import { Fields } from '../Filter/models';

export const fieldDelimiter = ':';
export const valueDelimiter = ',';

interface Input {
  data: Date | null | boolean;
  field: Fields;
}
export interface ClearFields {
  input: Array<Input>;
  search: string;
}
