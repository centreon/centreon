import { QueryParameter } from '../../queryParameters/models';

export interface BuildListingEndpointParameters {
  baseEndpoint?: string;
  customQueryParameters?: Array<QueryParameter>;
  parameters: Parameters;
}

export interface SearchMatch {
  field: string;
  value: string;
}

export interface Parameters {
  customQueryParameters?: Array<QueryParameter>;
  limit?: number;
  page?: number;
  search?: SearchParameter;
  sort?: SortQueryParameterValue;
}

export interface SearchParameter {
  conditions?: Array<ConditionsSearchParameter>;
  lists?: Array<ListsSearchParameter>;
  regex?: RegexSearchParameter;
}

export interface ListsSearchQueryParameterValue {
  $and: Array<{ [field: string]: { [field: string]: { $in: Array<string> } } }>;
}

export interface SortQueryParameterValue {
  [sortf: string]: string;
}

export interface RegexSearchParameter {
  fields: Array<string>;
  value: string;
}

export interface ListsSearchParameter {
  field: string;
  values: Array<string | number>;
}

export type Operator =
  | '$eq'
  | '$neq'
  | '$lt'
  | '$le'
  | '$gt'
  | '$ge'
  | '$lk'
  | '$nk'
  | '$in'
  | '$ni';

export type ConditionValue = {
  [value in Operator]?: string | Array<string>;
};

export interface ConditionsSearchParameter {
  field: string;
  value?: unknown;
  values?: ConditionValue;
}

type SearchPatterns = Array<{ [field: string]: { $rg: string } }>;

export interface OrSearchQueryParameterValue {
  $or: SearchPatterns;
}

export interface AndSearchQueryParameterValue {
  $and: SearchPatterns;
}

export type RegexSearchQueryParameterValue =
  | OrSearchQueryParameterValue
  | AndSearchQueryParameterValue
  | undefined;

export interface GetListsSearchQueryParameterValueProps {
  $and: Array<Record<string, FieldInValues>>;
}

export interface GetConditionsSearchQueryParameterValueState {
  $and: Array<Record<string, unknown>>;
}

export type SearchQueryParameterValue =
  | {
      $and: Array<
        | RegexSearchQueryParameterValue
        | GetListsSearchQueryParameterValueProps
        | GetConditionsSearchQueryParameterValueState
      >;
    }
  | RegexSearchQueryParameterValue
  | GetListsSearchQueryParameterValueProps
  | GetConditionsSearchQueryParameterValueState
  | undefined;

export interface FieldInValues {
  $in: Array<unknown>;
}
