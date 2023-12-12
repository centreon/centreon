import type { ListsSearchParameter, RegexSearchParameter } from '@centreon/ui';

export interface Search {
  lists?: Array<ListsSearchParameter>;
  regex?: RegexSearchParameter;
}
