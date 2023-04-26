import {
  isEmpty,
  isNil,
  reject,
  prop,
  head,
  toPairs,
  pipe,
  map,
  flatten
} from 'ramda';

import {
  SearchMatch,
  RegexSearchParameter,
  RegexSearchQueryParameterValue,
  SearchParameter,
  SearchQueryParameterValue,
  ConditionsSearchParameter,
  GetListsSearchQueryParameterValueProps,
  GetConditionsSearchQueryParameterValueState
} from './models';

const getFoundFields = ({
  value,
  fields
}: RegexSearchParameter): Array<SearchMatch> => {
  const fieldMatches = fields.map((field) => {
    const pattern = `(?:^|\\s)${field.replace('.', '\\.')}:([^\\s]+)`;

    const [, valueMatch] = value?.match(pattern) || [];

    return { field, value: valueMatch };
  });

  return fieldMatches.filter(prop('value'));
};

const getRegexSearchQueryParameterValue = (
  regex: RegexSearchParameter | undefined
): RegexSearchQueryParameterValue => {
  if (regex === undefined) {
    return undefined;
  }

  const foundFields = getFoundFields(regex);

  if (!isEmpty(foundFields)) {
    return {
      $and: foundFields.map(({ field, value }) => ({
        [field]: { $rg: value }
      }))
    };
  }

  const { value, fields } = regex;

  return {
    $or: fields.map((field) => ({
      [field]: { $rg: value }
    }))
  };
};

const getListsSearchQueryParameterValue = (
  lists
): GetListsSearchQueryParameterValueProps | undefined => {
  if (lists === undefined) {
    return undefined;
  }

  return {
    $and: lists.map(({ field, values }) => ({
      [field]: { $in: values }
    }))
  };
};

const getConditionsSearchQueryParameterValue = (
  conditions: Array<ConditionsSearchParameter> | undefined
): GetConditionsSearchQueryParameterValueState | undefined => {
  if (conditions === undefined) {
    return undefined;
  }

  const toIndividualOperatorValues = ({
    field,
    values,
    value
  }: ConditionsSearchParameter): Array<Record<string, unknown>> => {
    if (!isNil(value)) {
      return [
        {
          [field]: value
        }
      ];
    }

    return toPairs(values || {}).map(([operator, operatorValue]) => ({
      [field]: {
        [operator]: operatorValue
      }
    }));
  };

  return {
    $and: pipe(map(toIndividualOperatorValues), flatten)(conditions)
  };
};

const getSearchQueryParameterValue = (
  search: SearchParameter | undefined
): SearchQueryParameterValue => {
  if (search === undefined) {
    return undefined;
  }

  const { regex, lists, conditions } = search;

  const regexSearchParam = getRegexSearchQueryParameterValue(regex);
  const listSearchesParam = getListsSearchQueryParameterValue(lists);
  const conditionSearchesParam =
    getConditionsSearchQueryParameterValue(conditions);

  const result = reject(isNil, [
    regexSearchParam,
    listSearchesParam,
    conditionSearchesParam
  ]);

  if (result.length === 1) {
    return head(result);
  }

  return { $and: result };
};

export default getSearchQueryParameterValue;
