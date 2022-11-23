import { always, cond, equals } from 'ramda';

import { LoadingSkeleton } from '../..';

import { InputProps, InputType } from './models';

const getSkeleton = cond<InputType, JSX.Element>([
  [
    equals(InputType.Switch) as (b: InputType) => boolean,
    always(<LoadingSkeleton height={38} />)
  ],
  [
    equals(InputType.Radio) as (b: InputType) => boolean,
    always(<LoadingSkeleton height={104} />)
  ],
  [
    equals(InputType.Text) as (b: InputType) => boolean,
    always(<LoadingSkeleton height={52} />)
  ],
  [
    equals(InputType.SingleAutocomplete) as (b: InputType) => boolean,
    always(<LoadingSkeleton height={52} />)
  ],
  [
    equals(InputType.MultiAutocomplete) as (b: InputType) => boolean,
    always(<LoadingSkeleton height={52} />)
  ],
  [
    equals(InputType.Password) as (b: InputType) => boolean,
    always(<LoadingSkeleton height={52} />)
  ],
  [
    equals(InputType.MultiConnectedAutocomplete) as (b: InputType) => boolean,
    always(<LoadingSkeleton height={52} />)
  ],
  [
    equals(InputType.SingleConnectedAutocomplete) as (b: InputType) => boolean,
    always(<LoadingSkeleton height={52} />)
  ],
  [
    equals(InputType.FieldsTable) as (b: InputType) => boolean,
    always(<LoadingSkeleton height={52} />)
  ],
  [
    equals(InputType.Grid) as (b: InputType) => boolean,
    always(<LoadingSkeleton height={52} />)
  ],
  [
    equals(InputType.Custom) as (b: InputType) => boolean,
    always(<LoadingSkeleton height={52} />)
  ]
]);

interface Props {
  input: InputProps;
}

const LoadingSkeletonInput = ({ input }: Props): JSX.Element => {
  return <div>{getSkeleton(input.type)}</div>;
};

export default LoadingSkeletonInput;
