import {
  equals,
  flatten,
  head,
  isEmpty,
  isNil,
  pluck,
  prop,
  reject,
  toPairs,
  uniq
} from 'ramda';

import {
  ConditionsSearchParameter,
  GetConditionsSearchQueryParameterValueState,
  GetListsSearchQueryParameterValueProps,
  RegexSearchParameter,
  RegexSearchQueryParameterValue,
  SearchMatch,
  SearchParameter,
  SearchQueryParameterValue
} from './models';

export const getFoundFields = ({
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

  const fields = uniq(pluck('field', conditions));

  const toIndividualOperatorValues = (
    listField: string
  ): { $or: Array<Record<string, unknown>> } => {
    const filteredItems = conditions.filter(({ field }) =>
      equals(listField, field)
    );

    return {
      $or: flatten(
        filteredItems.map(({ value, values }) => {
          if (!isNil(value)) {
            return [
              {
                [listField]: value
              }
            ];
          }

          return toPairs(values || {}).map(([operator, operatorValue]) => ({
            [listField]: {
              [operator]: operatorValue
            }
          }));
        })
      )
    };
  };

  return {
    $and: fields.map(toIndividualOperatorValues)
  };
};

export const getSearchQueryParameterValue = (
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
