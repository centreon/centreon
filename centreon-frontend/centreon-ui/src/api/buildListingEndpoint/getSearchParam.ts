import { isEmpty } from 'ramda';
import {
  SearchObject,
  AndSearchParam,
  OrSearchParam,
  SearchInput,
} from './models';

const getFoundSearchObjects = ({
  searchValue,
  searchOptions = [],
}: SearchInput): Array<SearchObject> => {
  const searchOptionMatches = searchOptions.map((searchOption) => {
    const pattern = `${searchOption.replace('.', '\\.')}:([^\\s]+)`;

    const [, searchOptionMatch] = searchValue?.match(pattern) || [];

    return { field: searchOption, value: searchOptionMatch };
  });

  return searchOptionMatches.filter(({ value }) => value);
};

const getSearchParam = ({
  searchValue,
  searchOptions = [],
}: SearchInput): OrSearchParam | AndSearchParam | undefined => {
  if (!searchValue) {
    return undefined;
  }

  const foundSearchObjects = getFoundSearchObjects({
    searchValue,
    searchOptions,
  });

  if (!isEmpty(foundSearchObjects)) {
    return {
      $and: foundSearchObjects.map(({ field, value }) => ({
        [field]: { $rg: `${value}` },
      })),
    };
  }

  return {
    $or: searchOptions.map((searchOption) => ({
      [searchOption]: { $rg: `${searchValue}` },
    })),
  };
};

export default getSearchParam;
