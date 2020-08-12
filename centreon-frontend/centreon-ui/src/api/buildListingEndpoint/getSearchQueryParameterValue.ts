import { isEmpty, isNil, reject, prop, head } from 'ramda';

import {
  SearchMatch,
  RegexSearchParameter,
  RegexSearchQueryParameterValue,
  SearchParameter,
  ListsSearchQueryParameterValue,
  SearchQueryParameterValue,
} from './models';

const getFoundFields = ({
  value,
  fields,
}: RegexSearchParameter): Array<SearchMatch> => {
  const fieldMatches = fields.map((field) => {
    const pattern = `${field.replace('.', '\\.')}:([^\\s]+)`;

    const [, valueMatch] = value?.match(pattern) || [];

    return { field, value: valueMatch };
  });

  return fieldMatches.filter(prop('value'));
};

const getRegexSearchQueryParameterValue = (
  regex: RegexSearchParameter | undefined,
): RegexSearchQueryParameterValue => {
  if (regex === undefined) {
    return undefined;
  }

  const foundFields = getFoundFields(regex);

  if (!isEmpty(foundFields)) {
    return {
      $and: foundFields.map(({ field, value }) => ({
        [field]: { $rg: value },
      })),
    };
  }

  const { value, fields } = regex;

  return {
    $or: fields.map((field) => ({
      [field]: { $rg: value },
    })),
  };
};

const getListsSearchQueryParameterValue = (lists) => {
  if (lists === undefined) {
    return undefined;
  }

  return {
    $and: lists.map(({ field, values }) => ({
      [field]: { $in: values },
    })),
  };
};

const getSearchQueryParameterValue = (
  search: SearchParameter | undefined,
): SearchQueryParameterValue => {
  if (search === undefined) {
    return undefined;
  }

  const { regex, lists } = search;

  const regexSearchParam = getRegexSearchQueryParameterValue(regex);
  const listSearchesParam = getListsSearchQueryParameterValue(lists);

  const result = reject<
    RegexSearchQueryParameterValue | ListsSearchQueryParameterValue
  >(isNil, [regexSearchParam, listSearchesParam]);

  if (result.length === 1) {
    return head(result);
  }

  return { $and: result };
};

export default getSearchQueryParameterValue;
