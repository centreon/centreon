import { InputType } from '@centreon/ui';

import type { TFunction } from 'i18next';
import { JsonDecoder } from 'ts.data.json';
import { string } from 'yup';

import { getBlock } from '../../../ResourceForm/grid';
import type { FieldBlock, ResourceOptions } from '../../../ResourceForm/models';
import {
  labelMustBeAtMostCharacters,
  labelRequired
} from '../../../ResourceForm/translatedLabels';
import {
  labelAlias,
  labelHostBasicInformation,
  labelName
} from '../../translatedLabels';

// Uniqueness among hosts and templates is the server's: a client check goes
// stale the moment someone else creates one.
const nameMaxLength = 200;
const aliasMaxLength = 200;

// What the server stores: trimmed, each space turned into `_`.
const normalizeName = (name: string | undefined): string =>
  (name ?? '').trim().replace(/ /g, '_');

interface HostTemplateIdentityDetail {
  alias: string;
  name: string;
}

// Trimmed as the server normalises them, so blanks report as missing.
const getTrimmedSchema = ({
  label,
  max,
  t
}: {
  label: string;
  max: number;
  t: TFunction;
}) =>
  string()
    .trim()
    .max(max, t(labelMustBeAtMostCharacters, { label: t(label), max }))
    .required(t(labelRequired));

export const getHostTemplateIdentityBlock = ({
  resource: { idPrefix }
}: {
  resource: ResourceOptions;
}): FieldBlock<HostTemplateIdentityDetail> => ({
  defaultValues: { alias: '', name: '' },
  detailDecoders: {
    alias: JsonDecoder.optional(JsonDecoder.nullable(JsonDecoder.string)).map(
      (value) => value ?? ''
    ),
    name: JsonDecoder.string
  },
  getInputs: ({ t }) => ({
    basicInformation: getBlock({
      // The design's four-column identity row, two of its fields removed.
      className: 'grid-cols-4',
      columns: [
        {
          dataTestId: `${idPrefix}-name`,
          fieldName: 'name',
          label: t(labelName),
          required: true,
          type: InputType.Text
        },
        {
          dataTestId: `${idPrefix}-alias`,
          fieldName: 'alias',
          label: t(labelAlias),
          required: true,
          type: InputType.Text
        }
      ],
      idPrefix,
      name: 'basic-information',
      title: t(labelHostBasicInformation)
    })
  }),
  getSchema: ({ t }) => ({
    alias: getTrimmedSchema({ label: labelAlias, max: aliasMaxLength, t }),
    name: getTrimmedSchema({ label: labelName, max: nameMaxLength, t })
  }),
  toPayload: (values) => {
    const { alias, name } = values as { alias?: string; name?: string };

    return { alias: (alias ?? '').trim(), name: normalizeName(name) };
  }
});
